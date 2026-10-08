@extends('layouts.site')
@section('title', 'Notifications')
@section('content')
<div class="page-heading"><div><span class="eyebrow">YOUR LITTLE UPDATES</span><h1>Notifications</h1><p>Conversations, milestones, rewards, and community messages.</p></div><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="button secondary" type="submit">Mark all as read</button></form></div>
<nav class="report-tabs" aria-label="Notification filters"><a href="{{ route('notifications.index') }}" @class(['active' => $filter === 'all'])>All</a><a href="{{ route('notifications.index', ['filter' => 'unread']) }}" @class(['active' => $filter === 'unread'])>Unread</a><a href="{{ route('warnings.mine') }}">Moderation messages</a></nav>
@forelse($notifications as $notification)
<article @class(['panel', 'notification-card', 'notification-unread' => !$notification->read_at, 'notification-warning' => $notification->kind === 'warning'])>
    <div><span class="badge">{{ str_replace('_', ' ', $notification->kind) }}</span>@unless($notification->read_at)<span class="unread-dot" aria-label="Unread"></span>@endunless<h2>{{ $notification->title }}</h2><p class="plain-text">{{ $notification->message }}</p><time class="small muted" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->timezone(config('engagement.timezone'))->format('M j, Y · g:i a') }} PHT</time></div>
    <form method="POST" action="{{ route('notifications.open', $notification) }}">@csrf<button class="button secondary small" type="submit">Open →</button></form>
</article>
@empty<div class="panel empty-state"><h2>All quiet for now.</h2><p>{{ $filter === 'unread' ? 'You’re all caught up.' : 'Your next little milestone will show up here.' }}</p></div>@endforelse
@include('partials.pagination', ['items' => $notifications])
@endsection
