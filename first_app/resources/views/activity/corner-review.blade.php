@extends('layouts.site')
@section('title', 'Corner requests')
@section('content')
<div class="page-heading"><div><span class="eyebrow">MEMBERS BUILDING SOMETHING</span><h1>Corner requests</h1><p>Review requests earned through 14 different posting days.</p></div></div>
<form class="panel" method="GET"><label class="field">Status<select name="status" data-auto-submit>@foreach(['pending', 'approved', 'declined', 'all'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label><button class="button secondary small" type="submit">Filter</button></form>
@forelse($requests as $corner)<article class="panel"><div class="section-heading"><h2>{{ $corner->name }}</h2><span class="badge">{{ ucfirst($corner->status) }}</span></div><p class="small">Requested by <a href="{{ route('profiles.show', $corner->user) }}">{{ $corner->user->username }}</a></p><p class="plain-text">{{ $corner->description }}</p>
@if($corner->status === 'pending')<form method="POST" action="{{ route('corners.update', $corner) }}">@csrf @method('PATCH')<label class="field">Decision<select name="status"><option value="approved">Approve request</option><option value="declined">Decline request</option></select></label><label class="field">Response to the member<textarea name="review_note" maxlength="2000" required rows="3"></textarea></label><p class="small muted">Approval records permission. It does not create a Corner page yet.</p><button class="button" type="submit">Save decision & notify</button></form>@else<p class="plain-text staff-note">{{ $corner->review_note }}</p>@endif
</article>@empty<div class="panel empty-state"><h2>No requests in this queue.</h2></div>@endforelse
@include('partials.pagination', ['items' => $requests])
@endsection
