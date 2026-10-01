<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\AccountSessions;
use App\Support\PostFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MemberProfileController extends Controller
{
    use ProfileValidationRules;

    public function edit(Request $request): View
    {
        return view('profile', ['member' => $request->user()->loadCount('posts')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $request->user()->refresh();
        // PATCH supports partial profile updates; PUT remains the complete form.
        if ($request->isMethod('PATCH')) {
            foreach (['username', 'email'] as $field) {
                if (! $request->exists($field)) {
                    $request->merge([$field => $member->getAttribute($field)]);
                }
            }
        }
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'username' => Str::lower(trim((string) $request->input('username'))),
        ]);
        $data = $request->validate([
            'username' => $this->usernameRules($member->id, $request->input('username') === $member->username ? min(6, strlen($member->username)) : 6),
            'email' => $this->emailRules($member->id),
            'password' => ['nullable', 'string', 'min:8', 'max:128', 'confirmed'],
            'current_password' => [$request->filled('password') ? 'required' : 'nullable', 'current_password'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_avatar' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'profile_visibility' => ['sometimes', Rule::in(['public', 'private'])],
            'email_visible' => ['sometimes', 'boolean'],
            'profile_background' => ['sometimes', Rule::in(['default', 'solid', 'gradient'])],
            'profile_color_one' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'profile_color_two' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        if (Str::lower($member->email) === 'admin@gmail.com' && $data['email'] !== 'admin@gmail.com') {
            throw ValidationException::withMessages(['email' => 'The main administrator must keep admin@gmail.com.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        unset($data['current_password'], $data['avatar'], $data['remove_avatar']);

        if (! $member->hasPremium()) {
            foreach (['profile_background', 'profile_color_one', 'profile_color_two'] as $field) {
                if (isset($data[$field]) && $data[$field] !== $member->getAttribute($field)) {
                    throw ValidationException::withMessages([$field => 'An active Premium subscription is needed to customize your profile background.']);
                }
            }
        }

        $oldPath = $member->avatar_path;
        $newPath = null;
        if ($request->hasFile('avatar')) {
            $newPath = $request->file('avatar')->store('avatars', 'public');
            if ($newPath === false) {
                throw ValidationException::withMessages(['avatar' => 'Your picture could not be saved. Please try again.']);
            }
            $data['avatar_path'] = $newPath;
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar_path'] = null;
        }

        try {
            DB::transaction(function () use ($request, $member, $data) {
                // Uploads also lock the owner. Finish protecting legacy files before
                // committing a private profile, including concurrent uploads.
                $member = User::query()->lockForUpdate()->findOrFail($member->id);
                if (($data['profile_visibility'] ?? $member->profile_visibility) === 'private') {
                    try {
                        foreach ($member->posts()->pluck('media_path') as $path) {
                            PostFiles::protect($path);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        throw ValidationException::withMessages(['profile_visibility' => 'Your existing uploads could not be protected. Your privacy setting was not changed. Please ask the administrator to check media storage.']);
                    }
                }
                $oldEmail = $member->email;
                if ($oldEmail !== $data['email']) {
                    $member->email_verified_at = null;
                }
                $member->forceFill($data)->save();
                if (isset($data['password']) || $oldEmail !== $data['email']) {
                    AccountSessions::clearPasswordResets($oldEmail);
                    AccountSessions::revoke($member, $request->session()->getId());
                }
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath && array_key_exists('avatar_path', $data) && $oldPath !== $data['avatar_path']) {
            Storage::disk('public')->delete($oldPath);
        }
        if (isset($data['password'])) {
            $request->session()->regenerate();
        }

        $request->user()->refresh();

        return to_route('profile.edit')->with('success', 'Profile saved.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $member = $request->user();
        // Admin removal is handled by another admin in account management.
        abort_if($member->isAdmin(), 403, 'Administrators cannot delete their own account.');
        $paths = DB::transaction(function () use ($member) {
            $member = User::query()->lockForUpdate()->findOrFail($member->id);
            abort_if($member->isAdmin(), 403);
            $paths = $member->posts()->pluck('media_path')->all();
            if ($member->avatar_path) {
                $paths[] = $member->avatar_path;
            }
            AccountSessions::revoke($member);
            $member->posts()->delete();
            $member->delete();

            return $paths;
        });
        PostFiles::delete($paths);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home')->with('success', 'Your account and posts have been deleted.');
    }
}
