export function reportNeedsDescription(reason) {
    return reason.startsWith('other_');
}

export function profileBackground(mode, first, second) {
    const hex = /^#[0-9a-f]{6}$/i;
    if (!hex.test(first) || !hex.test(second)) return '#f8f6fa';
    if (mode === 'solid') return first;
    if (mode === 'gradient')
        return `linear-gradient(135deg, ${first}, ${second})`;
    return '#f8f6fa';
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-report-form]').forEach((form) => {
        const reason = form.querySelector('[data-report-reason]');
        const description = form.querySelector('[data-report-description]');
        const label = form.querySelector('[data-report-description-label]');
        const update = () => {
            description.required = reportNeedsDescription(reason.value);
            label.textContent = description.required
                ? 'Required for Other'
                : 'Optional';
        };
        reason.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-character-count]').forEach((field) => {
        const output = document.getElementById(field.dataset.characterCount);
        if (!output) return;
        const update = () => {
            output.textContent = `${Array.from(field.value).length.toLocaleString()} / ${field.maxLength.toLocaleString()}`;
        };
        field.addEventListener('input', update);
        update();
    });
    document.querySelectorAll('[data-profile-colors]').forEach((panel) => {
        const mode = panel.querySelector('[data-background-mode]');
        const first = panel.querySelector('[data-background-one]');
        const second = panel.querySelector('[data-background-two]');
        const preview = panel.querySelector('[data-background-preview]');
        const update = () => {
            preview.style.background = profileBackground(
                mode.value,
                first.value,
                second.value,
            );
        };
        [mode, first, second].forEach((input) =>
            input.addEventListener('input', update),
        );
        update();
    });
}
