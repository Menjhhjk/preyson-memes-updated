(() => {
    const videos = [...document.querySelectorAll('video')];
    videos.forEach((video) => {
        const feedback = video
            .closest('[data-video-playback]')
            ?.querySelector('[data-video-error]');
        if (!feedback) return;
        const showPlaybackError = () => {
            feedback.hidden = false;
        };
        video.addEventListener('error', showPlaybackError);
        video.addEventListener('loadeddata', () => {
            feedback.hidden = true;
        });
        // A cached response can fail before this script runs.
        if (video.error) showPlaybackError();
    });
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(({ target, intersectionRatio }) => {
                    if (intersectionRatio < 0.25) target.pause();
                });
            },
            { threshold: [0, 0.25] },
        );
        videos.forEach((video) => observer.observe(video));
    }
    videos.forEach((video) =>
        video.addEventListener('play', () => {
            videos.forEach((other) => {
                if (video !== other) other.pause();
            });
        }),
    );
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) videos.forEach((video) => video.pause());
    });
    window.addEventListener('pagehide', () =>
        videos.forEach((video) => video.pause()),
    );
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        }),
    );
    document
        .querySelectorAll('[data-auto-submit]')
        .forEach((control) =>
            control.addEventListener('change', () =>
                control.form.requestSubmit(),
            ),
        );
    document.querySelectorAll('.reaction-form').forEach((form) =>
        form.addEventListener('submit', async (event) => {
            if (!window.fetch) return;
            event.preventDefault();
            const card = form.closest('.post-card');
            const cards = [...document.querySelectorAll('.post-card')].filter(
                (other) => other.dataset.postId === card.dataset.postId,
            );
            const buttons = cards.flatMap((other) => [
                ...other.querySelectorAll('.reaction-button'),
            ]);
            const feedback = card.querySelector('.reaction-feedback');
            const data = new FormData(form);
            if (event.submitter?.name)
                data.set(event.submitter.name, event.submitter.value);
            buttons.forEach((button) => {
                button.disabled = true;
            });
            feedback.textContent = '';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok)
                    throw new Error(
                        Object.values(result.errors || {}).flat()[0] ||
                            result.message ||
                            'Could not save your reaction. Please try again.',
                    );
                buttons.forEach((button) => {
                    button.setAttribute(
                        'aria-pressed',
                        String(result.selected === button.dataset.emoji),
                    );
                    button.querySelector('.reaction-count').textContent =
                        result.counts[button.dataset.emoji] || '';
                });
                cards.forEach((card) => {
                    card.querySelector('.reaction-total').textContent =
                        result.total;
                    card.querySelector('.extra-reaction-summary').textContent =
                        ['🔥', '🎉', '🤯', '👏', '💀', '🥰']
                            .filter((emoji) => result.counts[emoji])
                            .map((emoji) => emoji + ' ' + result.counts[emoji])
                            .join(' ');
                });
                feedback.textContent = result.selected
                    ? 'Reaction saved.'
                    : 'Reaction removed.';
            } catch (error) {
                feedback.textContent =
                    error.message || 'Please refresh the page and try again.';
            } finally {
                buttons.forEach((button) => {
                    button.disabled = false;
                });
            }
        }),
    );
    const selectAll = document.querySelector('[data-select-all]');
    if (selectAll)
        selectAll.addEventListener('change', () =>
            document
                .querySelectorAll('input[name="post_ids[]"]')
                .forEach((input) => {
                    input.checked = selectAll.checked;
                }),
        );
    document.querySelectorAll('[data-avatar-input]').forEach((input) =>
        input.addEventListener('change', () => {
            const preview = document.querySelector('[data-avatar-preview]');
            if (preview && input.files[0]) {
                if (preview.dataset.objectUrl)
                    URL.revokeObjectURL(preview.dataset.objectUrl);
                preview.dataset.objectUrl = URL.createObjectURL(input.files[0]);
                preview.src = preview.dataset.objectUrl;
            }
        }),
    );
})();
