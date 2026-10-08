<div class="post-powers">
    <details class="super-picker"><summary>✦ Super-react <span data-power-balance="super">{{ $superBalance['total'] }}</span></summary>
        <div class="super-options"><p class="small muted">One charge per Super-reaction. Confirm before sending.</p>
        <div class="super-options-grid">
            @forelse($powerCatalog as $type)
            <form method="POST" action="{{ route('posts.super-react', $post) }}" data-power-form="super" data-power-name="{{ $type->name }}" data-power-confirm="Use one Super-reaction charge to send {{ $type->name }}?">@csrf
                <input type="hidden" name="request_key" value="{{ Str::uuid() }}"><input type="hidden" name="confirmed" value="1"><input type="hidden" name="super_reaction_type_id" value="{{ $type->id }}">
                <button class="super-option" type="submit" @disabled($superBalance['total'] === 0)><img src="{{ $type->effect()['gif'] }}" alt="" loading="lazy" width="88" height="88"><span>{{ $type->name }}</span></button>
            </form>
            @empty<p class="small muted">The Super-reaction library is taking a breather. Check back soon.</p>@endforelse
        </div>
        <a class="small" href="{{ route('rewards.index') }}">See charges and refresh dates →</a></div>
    </details>
    <form method="POST" action="{{ route('posts.boost', $post) }}" data-power-form="boost" data-power-confirm="Use one Boost charge? This adds one extra appearance in the default feed for 24 hours.">@csrf
        <input type="hidden" name="request_key" value="{{ Str::uuid() }}"><input type="hidden" name="confirmed" value="1">
        <button class="boost-button" type="submit" @disabled($boostBalance['total'] === 0)><img src="{{ asset('rocket.svg') }}" width="24" height="24" alt=""> Boost <span data-power-balance="boost">{{ $boostBalance['total'] }}</span></button>
    </form>
    <p class="power-feedback small" role="status" aria-live="polite"></p>
</div>
