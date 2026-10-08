<?php

namespace App\Http\Controllers;

use App\Models\SuperReactionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SuperReactionCatalogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('activity.super-catalog', ['types' => SuperReactionType::orderBy('id')->paginate(12)]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new SuperReactionType);
    }

    public function update(Request $request, SuperReactionType $type): RedirectResponse
    {
        return $this->save($request, $type);
    }

    private function save(Request $request, SuperReactionType $type): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $required = $type->exists ? 'nullable' : 'required';
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'is_active' => ['required', 'boolean'],
            'gif' => [$required, 'image', 'mimes:gif', 'extensions:gif', 'max:5120', 'dimensions:max_width=1500,max_height=1500'],
            'sound' => [$required, 'file', 'mimes:mp3,mpga,wav,ogg', 'extensions:mp3,wav,ogg', 'max:3072']]);
        $paths = [];
        try {
            $type->fill(['name' => $data['name'], 'is_active' => $data['is_active']]);
            if (! $type->exists) {
                $type->slug = (string) Str::uuid();
            }
            foreach (['gif', 'sound'] as $field) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('super-reactions', 'public');
                    if ($path === false) {
                        throw ValidationException::withMessages([$field => 'The asset could not be saved. Please try again.']);
                    }
                    $paths[] = $path;
                    $type->setAttribute($field.'_path', $path);
                }
            }
            $type->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        return to_route('super-catalog.index')->with('success', 'Super-reaction saved. Previous assets remain available to existing reaction receipts.');
    }
}
