<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ZipArchive;

class PostController extends Controller
{
    private function filenameTitle(string $name): string
    {
        // A filename is not a URL: keep literal plus signs, underscores,
        // percentages, punctuation, and Unicode exactly as supplied.
        return Str::limit(trim(pathinfo($name, PATHINFO_FILENAME)), 255, '') ?: 'Untitled Meme';
    }

    public function store(Request $request): RedirectResponse
    {
        $uploads = $request->file('media');
        $files = is_array($uploads) ? array_values($uploads) : [$uploads];
        foreach ($files as $upload) {
            $this->checkUpload($upload);
        }

        $isZip = count($files) === 1 && $files[0] instanceof UploadedFile
            && strtolower($files[0]->getClientOriginalExtension()) === 'zip';

        if ($isZip) {
            abort_unless($request->user()->isAdmin(), 403);
        }

        $rules = ['required', 'file', 'max:102400', $isZip ? 'mimes:zip' : 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov'];
        $request->validate(is_array($uploads) ? [
            'media' => ['required', 'array', 'min:1', 'max:6'],
            'media.*' => $rules,
            'title' => ['nullable', 'string', 'max:255'],
        ] : [
            'media' => $rules,
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_sum(array_map(fn ($file) => $file->getSize(), $files)) > 100 * 1024 * 1024) {
            throw ValidationException::withMessages(['media' => 'The selected files must total 100 MB or less.']);
        }

        if ($isZip) {
            return $this->importZip($request, $files[0]);
        }

        $paths = [];
        try {
            DB::transaction(function () use ($files, $request, &$paths) {
                $owner = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $limit = $owner->postLimit();
                if ($limit !== null && $owner->posts()->count() + count($files) > $limit) {
                    throw ValidationException::withMessages(['media' => "Your account can store {$limit} posts. Delete a post or activate Premium before adding more."]);
                }

                foreach ($files as $file) {
                    $path = $this->storeFile($file);
                    $paths[] = $path;
                    Post::create([
                        'user_id' => $request->user()->id,
                        'title' => $request->filled('title') ? $request->input('title') :
                            $this->filenameTitle($file->getClientOriginalName()),
                        'media_path' => $path,
                        'media_type' => $this->mediaType($file),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return $this->redirectAfterChange($request)->with('success', count($files).' meme(s) published successfully!');
    }

    private function checkUpload(mixed $upload): void
    {

        if ($upload instanceof UploadedFile && ! $upload->isValid()) {
            $message = match ($upload->getError()) {
                UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload limit of '.ini_get('upload_max_filesize').'.',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds the upload form size limit.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please select the file and try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary folder is missing or unavailable. Configure a writable upload_tmp_dir in php.ini and restart PHP.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file. Check temporary-folder permissions and available disk space.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload. Check the server configuration.',
                default => 'The file could not be uploaded. Please select it again and retry.',
            };

            throw ValidationException::withMessages(['media' => $message]);
        }
    }

    private function importZip(Request $request, UploadedFile $file): RedirectResponse
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['media' => 'Unable to read this ZIP archive.']);
        }

        $paths = [];
        $count = 0;
        $bytes = 0;
        $maxBytes = 100 * 1024 * 1024;

        try {
            if ($zip->numFiles > 1000) {
                throw ValidationException::withMessages(['media' => 'ZIP archives may contain at most 1,000 entries and 100 MB of extracted media.']);
            }

            DB::transaction(function () use ($zip, $request, &$paths, &$count, &$bytes, $maxBytes) {
                $owner = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($owner->isAdmin(), 403);

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if ($entryName === false || str_starts_with($entryName, '__MACOSX/')
                        || str_ends_with($entryName, '/') || str_starts_with(basename($entryName), '.')) {
                        continue;
                    }

                    if (! in_array(strtolower(pathinfo($entryName, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'], true)) {
                        continue;
                    }

                    $stat = $zip->statIndex($i);
                    if ($stat === false || $bytes + $stat['size'] > $maxBytes) {
                        throw ValidationException::withMessages(['media' => 'Extracted ZIP media must total 100 MB or less.']);
                    }

                    // Never extract archive paths. Copy into an anonymous temporary
                    // file, bound actual bytes, then verify MIME just like uploads.
                    $stream = $zip->getStream($entryName);
                    $temporary = tmpfile();
                    if ($stream === false || $temporary === false) {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        if (is_resource($temporary)) {
                            fclose($temporary);
                        }
                        throw ValidationException::withMessages(['media' => 'Unable to read an archive entry or create its temporary file.']);
                    }

                    try {
                        $copied = stream_copy_to_stream($stream, $temporary, $maxBytes - $bytes + 1);
                        if ($copied === false || $copied === 0 || $bytes + $copied > $maxBytes) {
                            throw ValidationException::withMessages(['media' => 'The archive contains empty, unreadable, or oversized media.']);
                        }
                        $bytes += $copied;
                        $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;
                        if ($temporaryPath === null) {
                            throw ValidationException::withMessages(['media' => 'Unable to locate the archive temporary file.']);
                        }
                        $entry = new UploadedFile($temporaryPath, basename($entryName), null, null, true);
                        Validator::make(['media' => $entry], [
                            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:102400'],
                        ])->validate();

                        $path = $this->storeFile($entry);
                        $paths[] = $path;
                        Post::create([
                            'user_id' => $owner->id,
                            'title' => $this->filenameTitle(basename($entryName)),
                            'media_path' => $path,
                            'media_type' => $this->mediaType($entry),
                        ]);
                        $count++;
                    } finally {
                        fclose($stream);
                        fclose($temporary);
                    }
                }

                if ($count === 0) {
                    throw ValidationException::withMessages(['media' => 'This archive does not contain supported images, GIFs, or videos.']);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        } finally {
            $zip->close();
        }

        return $this->redirectAfterChange($request)->with('success', "Batch import complete! {$count} memes added.");
    }

    private function storeFile(UploadedFile $file): string
    {
        $path = $file->store('memes', 'public');
        if ($path === false) {
            throw ValidationException::withMessages(['media' => 'The server could not save the file. Please check storage permissions and disk space.']);
        }

        return $path;
    }

    private function mediaType(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';

        return $mime === 'image/gif' ? 'gif' : (str_starts_with($mime, 'video/') ? 'video' : 'image');
    }

    public function edit(Request $request, Post $post): View
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);

        $returnTo = $request->input('return_to') === 'feed' ? 'feed' : 'dashboard';

        return view('edit-post', compact('post', 'returnTo'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);
        $this->checkUpload($request->file('media'));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:102400'],
        ]);
        unset($data['media']);
        $oldPath = $post->media_path;
        $newPath = null;
        if ($request->hasFile('media')) {
            $newPath = $this->storeFile($request->file('media'));
            $data['media_path'] = $newPath;
            $data['media_type'] = $this->mediaType($request->file('media'));
        }

        try {
            $post->update($data);
        } catch (\Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return $this->redirectAfterChange($request)->with('success', 'Post updated successfully!');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);
        $path = $post->media_path;
        $post->delete();
        Storage::disk('public')->delete($path);

        return $this->redirectAfterChange($request)->with('success', 'Post deleted successfully!');
    }

    private function redirectAfterChange(Request $request): RedirectResponse
    {
        return redirect()->route($request->input('return_to') === 'feed' ? 'home' : 'dashboard');
    }

    public function batchDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'post_ids' => 'required|array|min:1|max:1000',
            'post_ids.*' => 'required|integer|distinct|exists:posts,id',
        ]);

        $posts = Post::whereIn('id', $request->post_ids)->get();

        // Authorize the complete selection before deleting anything.
        foreach ($posts as $post) {
            abort_unless($post->canBeManagedBy($request->user()), 403);
        }

        DB::transaction(function () use ($posts) {
            foreach ($posts as $post) {
                $post->delete();
            }
        });
        Storage::disk('public')->delete($posts->pluck('media_path')->all());

        return $this->redirectAfterChange($request)->with('success', count($posts).' meme(s) deleted successfully!');
    }
}
