<dialog class="power-overlay" data-power-overlay aria-labelledby="power-effect-title" data-rocket-url="{{ asset('rocket.svg') }}" data-boost-sound="{{ asset('super-reactions/demo/boost.wav') }}">
    <button class="effect-close" type="button" data-effect-close aria-label="Close celebration">×</button>
    <div class="effect-stage"><img class="effect-media" data-effect-image alt=""><canvas class="effect-still" data-effect-still hidden></canvas></div>
    <div class="effect-caption"><h2 id="power-effect-title" data-effect-title></h2><p data-effect-hint>Enjoy the moment…</p><div class="effect-controls"><button type="button" data-effect-mute>Mute sound</button><button type="button" data-effect-play hidden>Play sound</button></div></div>
</dialog>
@if(session('power_effect'))<script type="application/json" data-power-receipt>{!! json_encode(session('power_effect'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>@endif
