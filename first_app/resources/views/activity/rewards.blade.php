@extends('layouts.site')
@section('title', 'Rewards & charges')
@section('content')
<div class="page-heading"><div><span class="eyebrow">A LITTLE SOMETHING FOR SHOWING UP</span><h1>14 days. Your own pace.</h1><p>Publish on 14 different days to unlock a Corner request. No streak to lose.</p></div><a class="button" href="{{ route('dashboard') }}">＋ Create a post</a></div>
<div class="panel reward-progress"><strong>{{ $days->count() }} / 14 posting days</strong><progress max="14" value="{{ $days->count() }}">{{ $days->count() }} / 14</progress><p class="small muted">Each Philippine calendar day counts once, starting with this feature’s launch. Multiple files or ZIP imports count as one day. Edits do not count, and deleting a post keeps your earned progress. Rewards are added automatically after a successful upload.</p></div>
<div class="charge-panels">
@foreach(['super' => ['Super-reactions', $superBalance], 'boost' => ['Boosts', $boostBalance]] as $kind => [$label, $balance])
<div class="panel charge-card"><span class="eyebrow">{{ strtoupper($label) }}</span><strong>{{ $balance['total'] }} <small>available</small></strong><p>{{ $balance['allowance'] }} / {{ $balance['limit'] }} {{ $balance['period'] }} + {{ $balance['bonus'] }} bonus</p><p class="small muted">Refreshes {{ \Carbon\CarbonImmutable::parse($balance['refresh'])->format('M j, Y · g:i a') }} PHT</p></div>
@endforeach
</div>
<p class="small muted">Each feature has its own allowance: Free gets 3 on the first of each month; Premium gets 5 every Monday. Unused allowance does not carry over. Bonus charges stay until used, and your allowance is spent first. Changing membership uses the remaining allowance for the current calendar period; it never refills on demand.</p>
<section class="reward-grid" aria-label="Posting day rewards">
@foreach($schedule as $number => $reward)
<article @class(['reward-tile', 'earned' => $days->has($number), 'next-reward' => $number === $days->count() + 1])><span class="reward-day">DAY {{ $number }}</span><span class="reward-symbol" aria-hidden="true">{{ isset($reward['corner']) ? '⌂' : (isset($reward['premium_days']) ? '✦' : (isset($reward['boost']) ? '↗' : '★')) }}</span><h2>{{ \App\Support\PostingRewards::label($reward) }}</h2>@if($days->has($number))<span class="reward-earned">✓ Earned {{ $days[$number]->posted_on->format('M j') }}</span>@elseif($number === $days->count() + 1)<span class="small">Your next reward</span>@else<span class="small muted">Waiting for you</span>@endif</article>
@endforeach
</section>
<section class="panel corner-request-panel" id="corner-request"><span class="eyebrow">DAY 14 · A PLACE OF YOUR OWN</span><h2>Request a Corner</h2><p class="muted">Tell the administrators what kind of Corner you’d like. This unlocks a request and review process; dedicated Corner pages are a future feature.</p>
@if($member->corner_unlocked_at)
    @if($canRequestCorner)<form method="POST" action="{{ route('corners.store') }}">@csrf<label class="field">Corner name<input name="name" maxlength="80" required value="{{ old('name') }}"></label><label class="field">What will your Corner be about?<textarea name="description" rows="4" required maxlength="2000">{{ old('description') }}</textarea></label><button class="button" type="submit">Send Corner request</button></form>@endif
@else<p class="notice">Complete {{ 14 - $days->count() }} more posting {{ Str::plural('day', 14 - $days->count()) }} to unlock this form.</p>@endif
@foreach($cornerRequests as $corner)<article class="corner-receipt"><div class="section-heading"><h3>{{ $corner->name }}</h3><span class="badge">{{ ucfirst($corner->status) }}</span></div><p class="plain-text">{{ $corner->description }}</p>@if($corner->review_note)<strong>Administrator’s response</strong><p class="plain-text">{{ $corner->review_note }}</p>@endif</article>@endforeach
@include('partials.pagination', ['items' => $cornerRequests])
</section>
@endsection
