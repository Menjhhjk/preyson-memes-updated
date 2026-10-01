@extends('layouts.site')
@section('title', 'Most reported')
@section('content')
@include('reports.filters')
<div class="notice ranking-explanation">@if($filters['sort'] === 'priority')<strong>Severity comes first.</strong> Compare Severe counts, then Moderate, then Minor. Any Severe report outranks any number of lower-severity reports; any Moderate outranks any number of Minor reports.@elseif($filters['sort'] === 'weighted')<strong>Weighted ranking.</strong> Each Severe report is worth 100 points, Moderate 10, and Minor 1.@else<strong>{{ $filters['sort'] === 'total' ? 'Ranked by total report count.' : 'Ranked by report date.' }}</strong>@endif <span>Counts include only reports matching the filters above. This is a review queue, not a finding of wrongdoing.</span></div>
<p class="small muted">{{ number_format($rankings->total()) }} {{ Str::plural('item', $rankings->total()) }} matching these filters.</p>
@forelse($rankings as $row)
@php($key = $row->target_type.':'.$row->target_id)
@php($target = $targets[$key] ?? null)
<article class="panel ranking-card">
    <span class="ranking-number">{{ $rankings->firstItem() + $loop->index }}</span><div class="ranking-content">
    <div class="section-heading"><span class="badge">{{ ucfirst($row->target_type) }} #{{ $row->target_id }}</span><span class="small muted">Last report {{ \Illuminate\Support\Carbon::parse($row->last_report)->format('M j, Y') }}</span></div>
    <h2>{{ $target ? \App\Support\ReportTargets::label($target) : $row->target_label }}</h2>
    <div class="report-counts"><span class="severity severe">{{ $row->severe_count }} Severe</span><span class="severity moderate">{{ $row->moderate_count }} Moderate</span><span class="severity minor">{{ $row->minor_count }} Minor</span><strong>{{ $row->total }} {{ Str::plural('report', $row->total) }}</strong>@if($filters['sort'] === 'weighted')<strong>{{ $row->score }} points</strong>@endif</div>
    <ul class="reason-breakdown">@foreach($breakdowns[$key] ?? [] as $reason => $count)<li>{{ $reasons[$reason] ?? $reason }} <strong>× {{ $count }}</strong></li>@endforeach</ul>
    <div class="form-actions"><a class="button secondary small" href="{{ route('reports.index', [...$filters, 'type' => $row->target_type, 'target' => $row->target_id, 'sort' => 'severity']) }}">Review reports</a>@if($target)<a class="small" href="{{ \App\Support\ReportTargets::url($target) }}">View {{ $row->target_type }} ↗</a>@else<span class="small muted">Content removed · reports retained</span>@endif</div>
    </div>
</article>
@empty<div class="panel empty-state"><h2>A quiet queue.</h2><p>No reported items match these filters.</p></div>@endforelse
@include('partials.pagination', ['items' => $rankings])
@endsection
