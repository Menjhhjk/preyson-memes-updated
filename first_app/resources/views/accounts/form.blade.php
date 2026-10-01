@extends('layouts.site')
@section('title', $account->exists ? 'Edit Account' : 'Create Account')
@section('content')
<div class="page-heading"><div><p class="muted">ADMIN WORKSPACE</p><h1>{{ $account->exists ? 'Edit account' : 'Create an account' }}</h1><p class="muted">A made-up email address is fine. No verification email is sent.</p></div><a class="button secondary" href="{{ route('accounts.index') }}">← Accounts</a></div>
<section class="panel" style="max-width:820px">
    @if($account->exists)<p class="muted">Joined {{ $account->created_at?->format('F j, Y') }} · {{ $account->posts_count }} posts · {{ $account->hasPremium() ? 'Premium until '.$account->premium_expires_at->format('F j, Y') : 'Free membership' }}</p>@endif
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}">
        @csrf
        @if($account->exists) @method('PUT') @endif
        <div class="form-grid">
            <label class="field">Username<input name="username" value="{{ old('username', $account->username) }}" required minlength="{{ $account->exists ? min(6, strlen($account->username)) : 6 }}" maxlength="50" pattern="[A-Za-z0-9_.\-]+" autocomplete="off"><small class="muted">New usernames need 6–50 characters: letters, numbers, dots, underscores, or hyphens.</small></label>
            <label class="field">Email<input type="email" name="email" value="{{ old('email', $account->email) }}" required maxlength="255" autocomplete="off" @readonly(strtolower($account->email ?? '') === 'admin@gmail.com')></label>
            <label class="field">Role<select name="role" required>@foreach(['member' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Administrator'] as $value => $label)<option value="{{ $value }}" @selected(old('role', $account->isAdmin() ? 'admin' : ($account->role ?? 'member')) === $value)>{{ $label }}</option>@endforeach</select><small class="muted">Moderators manage posts; administrators also manage accounts.</small></label>
            <div></div>
            <label class="field">{{ $account->exists ? 'New password (optional)' : 'Password' }}<input type="password" name="password" minlength="8" maxlength="128" autocomplete="new-password" @required(!$account->exists)><small class="muted">At least 8 characters. {{ $account->exists ? 'Leave blank to keep the current password.' : '' }}</small></label>
            <label class="field">Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="128" autocomplete="new-password" @required(!$account->exists)></label>
        </div>
        <button class="button" type="submit">{{ $account->exists ? 'Save account' : 'Create account' }}</button>
    </form>
</section>
@endsection
