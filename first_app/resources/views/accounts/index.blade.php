@extends('layouts.site')
@section('title', 'Account Management')
@section('content')
<div class="page-heading">
    <div><p class="muted">ADMIN WORKSPACE</p><h1>Account management</h1><p class="muted">Manage members, grant moderator access, and keep the community running.</p></div>
    <a class="button" href="{{ route('accounts.create') }}">+ Create account</a>
</div>
<section class="panel">
    <form method="GET" action="{{ route('accounts.index') }}" class="form-grid account-filters">
        <label class="field">Search accounts<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Username or email" maxlength="100"></label>
        <label class="field">Role<select name="role"><option value="">All roles</option>@foreach(['member' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Administrator'] as $value => $label)<option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="account-filter-actions"><button class="button" type="submit">Filter</button><a class="button secondary" href="{{ route('accounts.index') }}">Reset</a></div>
    </form>
    <p class="muted">{{ $accounts->total() }} {{ Str::plural('account', $accounts->total()) }} · Moderators can manage posts. Only administrators can manage accounts.</p>
    <div class="account-table-wrap">
        <table class="account-table">
            <thead><tr><th scope="col">Member</th><th scope="col">Role</th><th scope="col">Posts</th><th scope="col">Premium</th><th scope="col">Joined</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td><div class="account-identity"><img class="avatar" src="{{ $account->avatarUrl() }}" alt="" width="40" height="40" loading="lazy"><div><strong>{{ $account->username }}</strong><small>{{ $account->email }}</small></div></div></td>
                    <td><span class="badge">{{ $account->isAdmin() ? 'Admin' : ucfirst($account->role) }}</span></td>
                    <td>{{ $account->posts_count }} / {{ $account->postLimit() ?? '∞' }}</td>
                    <td>@if($account->hasPremium())<span class="badge">Premium</span><small>Until {{ $account->premium_expires_at->format('M j, Y') }}</small>@else<span class="muted">Free</span>@endif</td>
                    <td>{{ $account->created_at?->format('M j, Y') }}</td>
                    <td><div class="account-actions"><a class="button secondary" href="{{ route('accounts.edit', $account) }}">Edit</a>
                        @if(!$account->is(auth()->user()) && strtolower($account->email) !== 'admin@gmail.com')
                            <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Delete this account and all of its posts? This cannot be undone.');">@csrf @method('DELETE')<button class="button secondary account-danger" type="submit">Delete</button></form>
                        @else<span class="muted">Protected</span>@endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6">No accounts match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <nav class="account-pagination" aria-label="Account pages">
        @if($accounts->previousPageUrl())<a class="button secondary" href="{{ $accounts->previousPageUrl() }}">← Previous</a>@endif
        <span class="muted">Page {{ $accounts->currentPage() }} of {{ $accounts->lastPage() }}</span>
        @if($accounts->nextPageUrl())<a class="button secondary" href="{{ $accounts->nextPageUrl() }}">Next →</a>@endif
    </nav>
</section>
@endsection
@push('styles')
<style>
.account-filters{align-items:end;grid-template-columns:minmax(180px,2fr) minmax(120px,1fr) auto}.account-filter-actions,.account-actions,.account-pagination{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.account-table-wrap{overflow-x:auto}.account-table{width:100%;border-collapse:collapse;text-align:left}.account-table th,.account-table td{padding:18px 12px;border-bottom:1px solid var(--line,#e9e3ed);vertical-align:middle}.account-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.06em}.account-table small{display:block;margin-top:4px;font-size:.8rem;opacity:.75}.account-identity{display:flex;gap:12px;align-items:center;min-width:200px}.account-pagination{justify-content:center;margin-top:24px}.account-danger{color:var(--danger,#a62245)!important}@media(max-width:720px){.account-filters{grid-template-columns:1fr}}
</style>
@endpush
