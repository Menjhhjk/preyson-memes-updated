@extends('layouts.site')
@section('title', 'Premium & Support')
@section('content')
<div class="page-heading"><div><p class="muted">BIG MEME ENERGY. IMAGINARY MONEY.</p><h1>PreySON Premium <span aria-hidden="true">✦</span></h1><p class="muted">A little extra room to express your very serious lack of seriousness.</p></div><span class="badge">100% simulated</span></div>
@if($member->hasPremium())<div class="notice"><strong>Your Premium is active!</strong> Your benefits last until {{ $member->premium_expires_at->format('F j, Y, g:i a') }} ({{ config('app.timezone') }}). There is no automatic renewal.</div>@endif
<div class="premium-columns">
    <section class="panel premium-offer">
        <span class="badge">THE FULL MEME EXPERIENCE</span><h2>More room. More reactions.</h2>
        <p class="premium-price">₱0 <small>/ 30 days of pretend luxury</small></p>
        <ul class="premium-benefits"><li><strong>30 posts</strong><span>Keep up to 30 posts instead of the free limit of 6.</span></li><li><strong>More reactions</strong><span>Choose extra emoji through the More Reaction button.</span></li><li><strong>A little main-character energy</strong><span>Your username gets a moving gradient on your posts.</span></li><li><strong>A profile with personality</strong><span>Choose a solid background or a two-color gradient for your profile.</span></li><li><strong>30 days, then back to free</strong><span>Your existing posts stay. If you are over the free limit, delete posts or reactivate Premium before publishing more.</span></li></ul>
        @if($member->isAdmin())<p class="muted">Your administrator account already has unlimited posts. Premium still adds the gradient and extra reactions.</p>@endif
        <a class="button" href="#pretend-checkout">{{ $member->hasPremium() ? 'Send imaginary support' : 'Try simulated Premium' }} →</a>
    </section>
    <section class="panel" id="pretend-checkout">
        <h2>Very unofficial checkout</h2><p class="muted">Premium or a pretend donation. Either way, your wallet can take the day off.</p>
        <form method="POST" action="{{ route('premium.checkout') }}">
            @csrf
            <label class="field">What brings you here?<select name="intent" required><option value="subscribe" @selected(old('intent', $member->hasPremium() ? 'donate' : 'subscribe') === 'subscribe')>{{ $member->hasPremium() ? 'Premium is already active' : 'Activate Premium for 30 days' }}</option><option value="donate" @selected(old('intent', $member->hasPremium() ? 'donate' : 'subscribe') === 'donate')>Send an imaginary donation</option></select></label>
            <label class="field">Name for the applause<input name="name" value="{{ old('name', $member->username) }}" required maxlength="100" autocomplete="off" placeholder="Your legendary name"></label>
            <div class="pretend-payment" aria-label="Payment details crossed out because this is a simulation. No financial information is collected.">
                <div class="pretend-fields" aria-hidden="true"><span>Card number</span><div class="pretend-field">0000 &nbsp; 0000 &nbsp; 0000 &nbsp; NOPE</div><div class="pretend-row"><div><span>Expiry date</span><div class="pretend-field">NE / VER</div></div><div><span>Security code</span><div class="pretend-field">LOL</div></div></div><span>Billing address</span><div class="pretend-field">123 Imaginary Money Lane</div></div>
                <span class="pretend-cross" aria-hidden="true"></span>
            </div>
            <p class="checkout-disclaimer">Oops! This isn’t an actual subscription, alright?</p><p class="muted">No card, bank details, address, money, or payment service involved. Your name is only used to say thanks.</p>
            <button class="button" type="submit">Complete pretend checkout ✦</button>
        </form>
    </section>
</div>
@endsection
@push('styles')
<style>
.premium-columns{display:grid;grid-template-columns:1fr 1.1fr;gap:24px;align-items:start}.premium-offer{background:linear-gradient(145deg,rgba(123,94,233,.14),rgba(226,86,132,.08))}.premium-offer h2{font-size:2rem;line-height:1.15;max-width:350px}.premium-price{font-size:3rem;font-weight:800}.premium-price small{font-size:.82rem;font-weight:400;opacity:.7}.premium-benefits{padding:0;list-style:none;display:grid;gap:24px;margin:30px 0}.premium-benefits li{padding-left:25px;position:relative}.premium-benefits li:before{content:'✦';position:absolute;left:0;color:#a48cff}.premium-benefits strong,.premium-benefits span{display:block}.premium-benefits span{opacity:.72;font-size:.9rem;line-height:1.55;margin-top:6px}.pretend-payment{position:relative;margin:24px 0 18px;border:1px dashed #727789;border-radius:14px;padding:20px;overflow:hidden}.pretend-fields{opacity:.48;user-select:none}.pretend-fields>span,.pretend-row span{font-size:.78rem}.pretend-field{border:1px solid #727789;border-radius:8px;padding:12px;margin:7px 0 15px;font-family:monospace}.pretend-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}.pretend-cross{position:absolute;inset:20px;pointer-events:none}.pretend-cross:before,.pretend-cross:after{content:'';position:absolute;top:50%;left:-4%;width:108%;height:10px;border-radius:100px;background:#f56776;box-shadow:0 0 0 3px rgba(245,103,118,.13);transform:rotate(33deg)}.pretend-cross:after{transform:rotate(-33deg)}.checkout-disclaimer{font-size:1.2rem;font-weight:800;line-height:1.4;color:var(--danger,#a62245)}@media(max-width:850px){.premium-columns{grid-template-columns:1fr}}
</style>
@endpush