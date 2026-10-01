@extends('layouts.site')
@section('title', 'Your Profile')
@section('content')
<div class="page-heading"><div><p class="muted">YOUR CORNER OF THE INTERNET</p><h1>Your profile</h1><p class="muted">A familiar face for your next questionable meme.</p></div><a class="button secondary" href="{{ route('dashboard') }}">Your posts →</a></div>
<div class="member-profile-grid">
    <aside class="panel profile-summary">
        <img class="avatar profile-picture" data-avatar-preview src="{{ $member->avatarUrl() }}" alt="Your current profile picture" width="104" height="104">
        <h2>{{ $member->username }}</h2>
        <span class="badge">{{ $member->isAdmin() ? 'Administrator' : ucfirst($member->role) }}</span>
        <p>{{ $member->posts_count }} / {{ $member->postLimit() ?? 'unlimited' }} posts</p>
        @if($member->hasPremium())<span class="badge">✦ Premium</span><p class="muted">Until {{ $member->premium_expires_at->format('F j, Y, g:i a') }}</p>@else<p class="muted">Free member</p><a class="button secondary" href="{{ route('premium.index') }}">Explore Premium</a>@endif
        <p class="muted">Member since {{ $member->created_at->format('M Y') }}</p>
    </aside>
    <section class="panel">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <h2>Make it yours</h2>
            <label class="field">Profile picture<input data-avatar-input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, or WebP. Up to 2 MB and 6000 × 6000 pixels.</small></label>
            @if($member->avatar_path)<label class="profile-remove"><input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))> Remove current picture</label>@endif
            <div class="form-grid">
                <label class="field">Username<input name="username" value="{{ old('username', $member->username) }}" required minlength="{{ min(6, strlen($member->username)) }}" maxlength="50" pattern="[A-Za-z0-9_.\-]+" autocomplete="username"><small class="muted">New usernames need 6–50 characters: letters, numbers, dots, underscores, or hyphens.</small></label>
                <label class="field">Email<input type="email" name="email" value="{{ old('email', $member->email) }}" required maxlength="255" autocomplete="email" @readonly(strtolower($member->email) === 'admin@gmail.com')><small class="muted">This can be made up; it only identifies your account.</small></label>
            </div>
            <label class="field">About you<textarea name="description" rows="4" maxlength="1000" data-character-count="bio-count" placeholder="Your sense of humor, in a few words…">{{ old('description', $member->description) }}</textarea><small class="muted" id="bio-count" aria-live="polite">0 / 1,000</small></label>
            <h2>Your space, your choice</h2>
            <div class="form-grid">
                <label class="field">Profile visibility<select name="profile_visibility"><option value="public" @selected(old('profile_visibility', $member->profile_visibility) === 'public')>Public</option><option value="private" @selected(old('profile_visibility', $member->profile_visibility) === 'private')>Private</option></select><small class="muted">Private hides your bio, stats, and posts from visitors and the feed. Your username and avatar remain visible. You and the moderation team keep access. Comments you leave on other public posts stay visible.</small></label>
                <label class="field">Email visibility<select name="email_visible"><option value="0" @selected(!old('email_visible', $member->email_visible))>Hidden</option><option value="1" @selected(old('email_visible', $member->email_visible))>Visible on my profile</option></select><small class="muted">A private profile still hides your email from visitors. Administrators can manage account emails regardless of this setting.</small></label>
            </div>
            <div class="profile-customization">
                <h2>✦ Set the mood</h2><p class="muted small">Premium members can choose the background everyone sees on their profile.</p>
                @if($member->hasPremium())
                <div data-profile-colors>
                    <label class="field">Background<select name="profile_background" data-background-mode>@foreach(['default' => 'PreySON default', 'solid' => 'One solid color', 'gradient' => 'Two-color gradient'] as $value => $label)<option value="{{ $value }}" @selected(old('profile_background', $member->profile_background) === $value)>{{ $label }}</option>@endforeach</select></label>
                    <div class="form-grid"><label class="field">First color<input type="color" name="profile_color_one" value="{{ old('profile_color_one', $member->profile_color_one) }}" data-background-one></label><label class="field">Second color <small>Used for gradients</small><input type="color" name="profile_color_two" value="{{ old('profile_color_two', $member->profile_color_two) }}" data-background-two></label></div>
                    <div class="background-preview" data-background-preview><span>Profile background preview</span></div>
                </div>
                @else<p class="small muted">Your saved colors will return when Premium is active again.</p><a href="{{ route('premium.index') }}" class="button secondary small">Explore Premium</a>@endif
            </div>
            <h2>Change password</h2><p class="muted">Leave these fields blank to keep your password. Changing it signs out your other sessions.</p>
            <label class="field">Current password<input type="password" name="current_password" autocomplete="current-password"></label>
            <div class="form-grid">
                <label class="field">New password<input type="password" name="password" minlength="8" maxlength="128" autocomplete="new-password"><small class="muted">At least 8 characters.</small></label>
                <label class="field">Confirm new password<input type="password" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password"></label>
            </div>
            <div class="form-actions"><button class="button" type="submit">Save profile</button><a href="{{ route('profiles.show', $member) }}">View your profile ↗</a></div>
        </form>
    </section>
</div>
@unless($member->isAdmin())
<details class="panel"><summary>Delete my account</summary><p class="muted" style="margin-top:16px">This permanently removes your account, posts, pictures, and reactions. Enter your password to confirm.</p><form method="POST" action="{{ route('profile.destroy') }}" data-confirm="Permanently delete your account and all your posts?">@csrf @method('DELETE')<label class="field">Your password<input type="password" name="password" required autocomplete="current-password"></label><button class="button danger" type="submit">Delete my account</button></form></details>
@endunless
@endsection
@push('styles')
<style>.member-profile-grid{display:grid;grid-template-columns:minmax(210px,1fr) minmax(0,2.4fr);gap:24px;align-items:start}.profile-summary{text-align:center}.profile-picture{width:104px;height:104px;object-fit:cover;border-radius:28px}.profile-remove{display:flex;gap:8px;align-items:center;margin:12px 0 24px}.profile-remove input{width:auto}.member-profile-grid h2{margin-top:12px}@media(max-width:760px){.member-profile-grid{grid-template-columns:1fr}}</style>
@endpush
