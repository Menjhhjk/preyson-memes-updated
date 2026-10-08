(() => {
    const badge = document.querySelector('[data-notification-count]');
    if (badge) {
        const refresh = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(badge.dataset.countUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const { unread: count } = await response.json();
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count === 0;
            } catch {
                /* Keep the last known count while offline. */
            }
        };
        const timer = window.setInterval(refresh, 40000);
        window.addEventListener('pagehide', () => clearInterval(timer), {
            once: true,
        });
        document.addEventListener('visibilitychange', refresh);
    }

    const overlay = document.querySelector('[data-power-overlay]');
    let sound;
    let entrance;
    let ready = false;
    let muted = false;
    let previousFocus;
    try {
        muted = localStorage.getItem('preyson-effects-muted') === 'true';
    } catch {
        /* Storage is optional. */
    }
    const close = () => {
        if (overlay?.open) overlay.close();
    };
    const reducedMotion = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    async function celebrate(receipt, origin) {
        if (!overlay?.showModal) return;
        if (overlay.open) close();
        previousFocus = document.activeElement;
        ready = false;
        const picture = overlay.querySelector('[data-effect-image]');
        const still = overlay.querySelector('[data-effect-still]');
        const hint = overlay.querySelector('[data-effect-hint]');
        const play = overlay.querySelector('[data-effect-play]');
        const mute = overlay.querySelector('[data-effect-mute]');
        picture.classList.remove('effect-hover');
        picture.hidden = false;
        still.hidden = true;
        const boost = receipt.kind === 'boost';
        picture.src = boost ? overlay.dataset.rocketUrl : receipt.effect.gif;
        picture.alt = boost
            ? 'A rocket celebrating your boost'
            : receipt.effect.name;
        overlay.querySelector('[data-effect-title]').textContent = boost
            ? 'You boosted a post!'
            : `You Super-reacted "${receipt.effect.name}"!`;
        hint.textContent = 'Enjoy the moment...';
        mute.textContent = muted ? 'Unmute sound' : 'Mute sound';
        play.hidden = true;
        sound = new Audio(
            boost ? overlay.dataset.boostSound : receipt.effect.sound,
        );
        sound.volume = 0.35;
        sound.muted = muted;
        sound.play().catch(() => {
            if (overlay.open) play.hidden = false;
        });
        overlay.showModal();
        document.querySelectorAll('video').forEach((video) => video.pause());
        try {
            await picture.decode();
        } catch {
            /* A missing asset must not trap the dialog. */
        }
        if (!overlay.open) return;
        const box = picture.getBoundingClientRect();
        if (reducedMotion()) {
            if (!boost) {
                try {
                    still.width = picture.naturalWidth;
                    still.height = picture.naturalHeight;
                    still.getContext('2d').drawImage(picture, 0, 0);
                    still.hidden = false;
                    picture.hidden = true;
                } catch {
                    /* Cross-origin images can still be drawn without reading pixels. */
                }
            }
        } else if (picture.animate) {
            const dx = origin
                ? origin.x + origin.width / 2 - (box.x + box.width / 2)
                : 0;
            const dy = origin
                ? origin.y + origin.height / 2 - (box.y + box.height / 2)
                : 120;
            entrance = picture.animate(
                boost
                    ? [
                          {
                              transform:
                                  'translateX(-110vw) scale(.18) rotate(90deg)',
                              offset: 0,
                          },
                          {
                              transform:
                                  'translateX(85vw) scale(.4) rotate(90deg)',
                              offset: 0.5,
                          },
                          {
                              transform:
                                  'translate(30vw, 15vh) scale(.7) rotate(-20deg)',
                              offset: 0.7,
                          },
                          {
                              transform: 'translate(0, 0) scale(1) rotate(0)',
                              offset: 1,
                          },
                      ]
                    : [
                          {
                              transform: `translate(${dx}px, ${dy}px) scale(${origin ? origin.width / box.width : 0.2})`,
                              opacity: 0.6,
                          },
                          { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                      ],
                {
                    duration: boost ? 1800 : 900,
                    easing: 'cubic-bezier(.2,.65,.3,1)',
                },
            );
            try {
                await entrance.finished;
            } catch {
                return;
            }
            if (!overlay.open) return;
            picture.classList.add('effect-hover');
        }
        ready = true;
        hint.textContent = 'Click anywhere to close, or press Escape.';
    }
    if (overlay) {
        overlay
            .querySelector('[data-effect-close]')
            .addEventListener('click', close);
        overlay.addEventListener('click', (event) => {
            if (ready && !event.target.closest('button')) close();
        });
        overlay.addEventListener('close', () => {
            ready = false;
            entrance?.cancel();
            sound?.pause();
            overlay
                .querySelector('[data-effect-image]')
                .classList.remove('effect-hover');
            previousFocus?.focus();
        });
        overlay
            .querySelector('[data-effect-mute]')
            .addEventListener('click', (event) => {
                muted = !muted;
                if (sound) sound.muted = muted;
                event.currentTarget.textContent = muted
                    ? 'Unmute sound'
                    : 'Mute sound';
                try {
                    localStorage.setItem(
                        'preyson-effects-muted',
                        String(muted),
                    );
                } catch {
                    /* Optional. */
                }
            });
        overlay
            .querySelector('[data-effect-play]')
            .addEventListener('click', (event) => {
                sound
                    ?.play()
                    .then(() => {
                        event.target.hidden = true;
                    })
                    .catch(() => {
                        event.target.textContent = 'Sound unavailable';
                    });
            });
        const receipt = document.querySelector('[data-power-receipt]');
        if (receipt) {
            try {
                celebrate(JSON.parse(receipt.textContent));
            } catch {
                /* Invalid flash data. */
            }
        }
    }
    let pending = false;
    document.querySelectorAll('[data-power-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (!window.fetch) {
                if (!window.confirm(form.dataset.powerConfirm))
                    event.preventDefault();
                return;
            }
            event.preventDefault();
            if (pending || !window.confirm(form.dataset.powerConfirm)) return;
            pending = true;
            const kind = form.dataset.powerForm;
            const origin = form.querySelector('img')?.getBoundingClientRect();
            const feedback = form
                .closest('.post-powers')
                .querySelector('.power-feedback');
            const buttons = [
                ...document.querySelectorAll(
                    `[data-power-form="${kind}"] button`,
                ),
            ];
            const previousStates = buttons.map((button) => button.disabled);
            buttons.forEach((button) => {
                button.disabled = true;
            });
            feedback.textContent = 'Sending...';
            let succeeded = false;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok)
                    throw new Error(
                        Object.values(result.errors || {})
                            .flat()
                            .join(' ') ||
                            result.message ||
                            'Could not complete this action.',
                    );
                succeeded = true;
                document
                    .querySelectorAll(`[data-power-balance="${kind}"]`)
                    .forEach((node) => {
                        node.textContent = result.balance.total;
                    });
                buttons.forEach((button) => {
                    button.disabled = result.balance.total === 0;
                });
                // Retain the key on failures so retrying an uncertain request cannot spend twice.
                form.querySelector('[name="request_key"]').value =
                    '10000000-1000-4000-8000-100000000000'.replace(
                        /[018]/g,
                        (n) =>
                            (
                                Number(n) ^
                                (crypto.getRandomValues(new Uint8Array(1))[0] &
                                    (15 >> (Number(n) / 4)))
                            ).toString(16),
                    );
                feedback.textContent =
                    kind === 'boost'
                        ? 'Boosted for 24 hours. Refresh the default feed to see its extra appearance.'
                        : 'Super-reaction sent!';
                const picker = form.closest('details');
                if (picker) picker.open = false;
                celebrate(result, origin);
            } catch (error) {
                feedback.textContent =
                    error.message ||
                    'Connection interrupted. Please try again.';
            } finally {
                if (!succeeded)
                    buttons.forEach((button, index) => {
                        button.disabled = previousStates[index];
                    });
                pending = false;
            }
        });
    });
})();
