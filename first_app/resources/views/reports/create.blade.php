@extends('layouts.site')
@section('title', 'Report '.$type)
@section('content')
<div class="page-heading"><div><span class="eyebrow">LOOK OUT FOR EACH OTHER</span><h1>Report {{ $type === 'account' ? 'an account' : 'a '.$type }}</h1><p>Your report goes to the moderation team for review.</p></div><a class="button secondary" href="{{ $targetUrl }}">← Back</a></div>
<section class="panel report-form-panel">
    <span class="badge">{{ ucfirst($type) }} #{{ $targetId }}</span><h2 class="report-target-label">{{ $label }}</h2>
    @if($existing)
        <div class="notice success">You have already reported this {{ $type }}. Status: <strong>{{ str_replace('_', ' ', $existing->status) }}</strong>.</div><p class="muted">Each account can report an item once. The moderation team can still review your report.</p>
    @else
        <form method="POST" action="{{ route('reports.store', ['type' => $type, 'id' => $targetId]) }}" data-report-form>@csrf
            <label class="field" for="report-reason">What’s the concern?<select id="report-reason" name="reason" required data-report-reason><option value="">Choose a reason</option>@foreach($groups as $severity => $reasons)<optgroup label="{{ ucfirst($severity) }}">@foreach($reasons as $key => $label)<option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>@endforeach</optgroup>@endforeach</select><small class="muted">Choose the closest match. There’s an Other option in each severity group.</small></label>
            <div class="severity-guide"><span class="severity severe">Severe · urgent harm</span><span class="severity moderate">Moderate · harmful behavior</span><span class="severity minor">Minor · disruption</span></div>
            <label class="field" for="report-description">Description <small data-report-description-label>Optional, required for Other</small><textarea id="report-description" name="description" rows="6" maxlength="3000" data-report-description data-character-count="report-count" @required(str_starts_with((string) old('reason'), 'other_')) placeholder="Tell the moderation team what happened and what to look for.">{{ old('description') }}</textarea><small class="muted" id="report-count" aria-live="polite">0 / 3,000</small></label>
            <p class="small muted">Your identity and description are visible only to the moderation team. Reports help staff review concerns; they do not automatically remove content.</p>
            <button class="button" type="submit">⚑ Send report</button>
        </form>
    @endif
</section>
@endsection
