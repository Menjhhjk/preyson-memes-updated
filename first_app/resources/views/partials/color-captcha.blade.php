<style>
    .color-check { border: 1px solid #ddd6fe; border-radius: 12px; padding: 1rem; margin: 1rem 0; text-align: left; background: #faf7ff; }
    .color-check legend { padding: 0 .35rem; font-size: .95rem; font-weight: 750; color: #4c1d95; }
    .color-check .color-instructions { margin: 0 0 .8rem; font-size: .85rem; line-height: 1.5; color: #403c55; }
    .color-target { display: inline-block; height: 1.1em; width: 1.1em; vertical-align: -.15em; border-radius: 4px; border: 1px solid #0003; }
    .color-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; }
    .color-check .color-tile { position: relative; display: block; margin: 0; cursor: pointer; }
    .color-tile input { position: absolute; width: 1px; height: 1px; clip-path: inset(50%); overflow: hidden; }
    .color-tile .color-square { position: relative; display: grid; place-items: center; aspect-ratio: 1; border: 3px solid transparent; border-radius: 8px; box-shadow: inset 0 0 0 1px #0002; }
    .color-tile input:checked + .color-square { border-color: #171025; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #171025; }
    .color-tile input:focus-visible + .color-square { outline: 3px dashed #171025; outline-offset: 4px; }
    .color-tick { display: none; background: #fff; color: #171025; border-radius: 50%; width: 1.5rem; height: 1.5rem; text-align: center; line-height: 1.5rem; font-size: 1rem; }
    .color-tile input:checked + .color-square .color-tick { display: block; }
    .color-check .color-name { display: block; margin-top: .3rem; font-size: .7rem; font-weight: 500; color: #27203d; text-align: center; }
    .color-grid:not(.show-names) .color-name { display: none; }
    .color-check .color-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; justify-content: space-between; margin-top: .85rem; }
    .color-check .color-label-toggle { display: flex; align-items: center; gap: .35rem; margin: 0; font-size: .76rem; font-weight: 500; }
    .color-label-toggle input { width: 1rem; height: 1rem; accent-color: #6d28d9; }
    .color-refresh { background: #fff; color: #5b21b6; border: 1px solid #c4b5fd; padding: .4rem .55rem; border-radius: 6px; font: inherit; font-size: .76rem; cursor: pointer; }
    .color-check .color-status { margin: .65rem 0 0; font-size: .75rem; color: #514963; min-height: 1.1em; }
    .color-check :focus-visible { outline: 3px solid #6d28d9; outline-offset: 3px; }
</style>
<fieldset class="color-check" data-color-captcha data-purpose="{{ $captcha['purpose'] }}" data-refresh-url="{{ route('captcha.refresh') }}">
    <legend>Quick color check</legend>
    <p class="color-instructions">Select every <strong data-target-name>{{ $captcha['target'] }}</strong>
        <span class="color-target" data-target-color style="background-color: {{ $captcha['target_color'] }}" aria-hidden="true"></span>
        square. There are 2–6 matches; skip the pale mixed shades. Use Tab and Space to select.</p>
    <input type="hidden" name="captcha_id" value="{{ $captcha['id'] }}" data-captcha-id>
    <div class="color-grid" data-color-grid>
        @foreach ($captcha['tiles'] as $index => $tile)
            <label class="color-tile">
                <input type="checkbox" name="captcha_tiles[]" value="{{ $index }}" aria-label="Square {{ $index + 1 }}: {{ $tile['name'] }}">
                <span class="color-square" style="background-color: {{ $tile['color'] }}"><span class="color-tick" aria-hidden="true">✓</span></span>
                <span class="color-name" aria-hidden="true">{{ ucfirst($tile['name']) }}</span>
            </label>
        @endforeach
    </div>
    <div class="color-tools">
        <label class="color-label-toggle"><input type="checkbox" data-show-color-names> Show color names</label>
        <button type="button" class="color-refresh" data-refresh-captcha>New colors</button>
    </div>
    <p class="color-status" role="status" aria-live="polite" data-color-status>0 selected · valid for 10 minutes</p>
    <noscript><p class="color-status">Reload this page for a new challenge. Your current color check works without JavaScript.</p></noscript>
</fieldset>
<script>
(() => {
    const widget = document.currentScript.previousElementSibling;
    const grid = widget.querySelector('[data-color-grid]');
    const status = widget.querySelector('[data-color-status]');
    const refresh = widget.querySelector('[data-refresh-captcha]');
    const names = widget.querySelector('[data-show-color-names]');
    grid.addEventListener('change', () => {
        status.textContent = `${grid.querySelectorAll('input:checked').length} selected · select all matching squares`;
    });
    names.addEventListener('change', () => grid.classList.toggle('show-names', names.checked));
    refresh.addEventListener('click', async () => {
        refresh.disabled = true;
        status.textContent = 'Loading a fresh challenge…';
        try {
            const response = await fetch(widget.dataset.refreshUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': widget.closest('form').querySelector('[name="_token"]').value },
                body: JSON.stringify({ purpose: widget.dataset.purpose, previous_id: widget.querySelector('[data-captcha-id]').value })
            });
            if (!response.ok) throw new Error('Refresh failed');
            const challenge = await response.json();
            widget.querySelector('[data-captcha-id]').value = challenge.id;
            widget.querySelector('[data-target-name]').textContent = challenge.target;
            widget.querySelector('[data-target-color]').style.backgroundColor = challenge.target_color;
            grid.querySelectorAll('.color-tile').forEach((tile, index) => {
                const input = tile.querySelector('input');
                input.checked = false;
                input.setAttribute('aria-label', `Square ${index + 1}: ${challenge.tiles[index].name}`);
                tile.querySelector('.color-square').style.backgroundColor = challenge.tiles[index].color;
                tile.querySelector('.color-name').textContent = challenge.tiles[index].name;
            });
            status.textContent = `New challenge: select ${challenge.target}. 0 selected · valid for 10 minutes`;
        } catch (error) {
            status.textContent = 'Could not refresh. Please reload the page and try again.';
        } finally {
            refresh.disabled = false;
        }
    });
})();
</script>
