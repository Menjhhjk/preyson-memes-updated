@extends('layouts.site')
@section('title', 'Report logs')
@section('content')
@include('reports.filters')
<p class="muted small">{{ number_format($reports->total()) }} {{ Str::plural('report', $reports->total()) }} matching these filters. Reporter details and review notes are visible to staff only.</p>
@forelse($reports as $report)
@php($target = $targets[$report->target_type.':'.$report->target_id] ?? null)
<article class="panel report-entry" id="report-{{ $report->id }}">
    <header class="report-entry-heading"><div><span class="severity {{ $report->severity }}">{{ ucfirst($report->severity) }}</span> <span class="badge">{{ ucfirst($report->target_type) }} #{{ $report->target_id }}</span> <span class="badge status-{{ $report->status }}">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</span><h2>{{ $report->target_label }}</h2><p class="small muted">Report #{{ $report->id }} · {{ $report->created_at->format('M j, Y · g:i a') }} UTC · By @if($report->reporter)<a href="{{ route('profiles.show', $report->reporter) }}">{{ $report->reporter->username }}</a>@else Former member @endif</p></div>@if($target)<a class="button secondary small" href="{{ \App\Support\ReportTargets::url($target) }}">View {{ $report->target_type }} ↗</a>@else<span class="badge">Content removed</span>@endif</header>
    <strong>{{ $reasons[$report->reason] ?? $report->reason }}</strong><p class="plain-text">{{ $report->description ?: 'No additional description.' }}</p>
    <details class="report-snapshot"><summary>Content recorded when reported</summary><p class="plain-text">{{ $report->target_snapshot }}</p></details>
    <details class="report-review"><summary>Review report</summary><form method="POST" action="{{ route('reports.update', $report) }}">@csrf @method('PATCH')<label class="field">Status<select name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($report->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label><label class="field">Staff note<textarea name="moderator_note" rows="3" maxlength="3000">{{ $report->moderator_note }}</textarea></label><button class="button small" type="submit">Save review</button></form></details>
    @if($report->reviewed_at)<p class="small muted">Last reviewed by {{ $report->reviewer?->username ?? 'Former staff member' }} · {{ $report->reviewed_at->format('M j, Y · g:i a') }} UTC</p>@if($report->moderator_note)<p class="plain-text staff-note">{{ $report->moderator_note }}</p>@endif @endif
</article>
@empty<div class="panel empty-state"><h2>Nothing in this queue.</h2><p>No reports match the current filters.</p></div>@endforelse
@include('partials.pagination', ['items' => $reports])
@endsection
