<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PostController extends Controller
{
    private function cleanDriveFilename(string $name): string
    {
        // Decode URL encoded characters (e.g. %20 -> space, etc)
        $decoded = urldecode($name);

        // Fix common Google Drive export substitutions:
        // Converts "Don_t" or "ain_t" patterns back to natural contractions
        $restored = preg_replace('/(\b[a-zA-Z]+)_(t|s|d|ll|ve|re|m)\b/i', '$1\'$2', $decoded);

        return trim($restored);
    }

    public function store(Request $request)
    {
        $uploads = $request->file('media');
        $files = is_array($uploads) ? array_values($uploads) : [$uploads];
        foreach ($files as $upload) {
            $this->checkUpload($upload);
        }

        $isZip = count($files) === 1 && $files[0] instanceof UploadedFile
            && strtolower($files[0]->getClientOriginalExtension()) === 'zip';

        if ($isZip) {
            abort_unless($request->user()->is_admin, 403);
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
                foreach ($files as $file) {
                    $path = $this->storeFile($file);
                    $paths[] = $path;
                    Post::create([
                        'user_id' => $request->user()->id,
                        'title' => $request->filled('title') ? $request->input('title') :
                            (Str::limit($this->cleanDriveFilename(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 255, '') ?: 'Untitled Meme'),
                        'media_path' => $path,
                        'media_type' => $this->mediaType($file),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return redirect('/dashboard')->with('success', count($files).' meme(s) published successfully!');
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

    private function importZip(Request $request, UploadedFile $file)
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) === true) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];
            $count = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                if (str_starts_with($entryName, '__MACOSX/') || str_ends_with($entryName, '/') || str_starts_with(basename($entryName), '.')) {
                    continue;
                }

                $fileExt = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

                if (in_array($fileExt, $allowedExtensions)) {
                    $stream = $zip->getStream($entryName);
                    if ($stream) {
                        $storedPath = 'memes/'.Str::random(40).'.'.$fileExt;
                        try {
                            if (! Storage::disk('public')->put($storedPath, $stream)) {
                                throw ValidationException::withMessages(['media' => 'The server could not save an archive file. Check storage permissions and disk space.']);
                            }
                        } finally {
                            fclose($stream);
                        }

                        $rawFileName = pathinfo($entryName, PATHINFO_FILENAME);
                        $cleanTitle = $this->cleanDriveFilename($rawFileName);
                        $mediaType = in_array($fileExt, ['mp4', 'webm', 'mov']) ? 'video' : 'image';

                        Post::create([
                            'user_id' => $request->user()->id,
                            'title' => Str::limit($cleanTitle, 255, '') ?: 'Untitled Meme',
                            'media_path' => $storedPath,
                            'media_type' => $mediaType,
                        ]);

                        $count++;
                    }
                }
            }

            $zip->close();

            return redirect('/dashboard')->with('success', "Batch import complete! {$count} memes added.");
        }

        return back()->withErrors(['media' => 'Unable to read this ZIP archive.']);
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
        return in_array(strtolower($file->getClientOriginalExtension()), ['mp4', 'webm', 'mov']) ? 'video' : 'image';
    }

    public function edit(Request $request, Post $post)
    {
        abort_unless($post->canBeManagedBy($request->user()), 403);

        return view('edit-post', compact('post'));
    }

    public function update(Request $request, Post $post)
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

        return redirect('/dashboard')->with('success', 'Post updated successfully!');
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'post_ids' => 'required|array',
            'post_ids.*' => 'exists:posts,id',
        ]);

        $posts = Post::whereIn('id', $request->post_ids)->get();

        // Authorize the complete selection before deleting anything.
        foreach ($posts as $post) {
            abort_unless($post->canBeManagedBy($request->user()), 403);
        }

        foreach ($posts as $post) {
            Storage::disk('public')->delete($post->media_path);
            $post->delete();
        }

        return redirect('/dashboard')->with('success', count($posts).' meme(s) deleted successfully!');
    }
}
