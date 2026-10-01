const megabyte = 1024 * 1024;

function formatSize(bytes) {
    const unit = bytes >= 1024 * megabyte ? 1024 * megabyte : megabyte;
    const size = Math.ceil((bytes / unit) * 100) / 100;
    return `${size} ${unit === megabyte ? 'MB' : 'GB'}`;
}

export function checkUploadSelection(files, maxBytes, maxFiles) {
    const oversized = files.find((file) => file.size > maxBytes);
    if (oversized) {
        return `${oversized.name} is ${formatSize(oversized.size)}. The maximum is ${formatSize(maxBytes)} per file. Select a smaller file.`;
    }
    if (files.length > maxFiles) {
        return `Select up to ${maxFiles} files per upload.`;
    }
    const total = files.reduce((bytes, file) => bytes + file.size, 0);
    if (total > maxBytes) {
        return `The selected files total ${formatSize(total)}. The combined limit is ${formatSize(maxBytes)}. Select fewer or smaller files.`;
    }
    return '';
}

export function attachUploadLimit(form) {
    const input = form.querySelector('[data-media-input]');
    const feedback = form.querySelector('[data-upload-status]');
    if (!input || !feedback) return;
    const maxBytes = Number(input.dataset.maxUploadBytes);
    const maxFiles = Number(input.dataset.maxFiles);
    const buttons = [
        ...form.querySelectorAll('button[type="submit"], input[type="submit"]'),
    ];
    const alreadyDisabled = new Set(
        buttons.filter((button) => button.disabled),
    );

    const validate = () => {
        // Read file metadata only: a 4 GB selection never needs to be loaded or sent.
        const files = [...input.files];
        const error = checkUploadSelection(files, maxBytes, maxFiles);
        const total = files.reduce((bytes, file) => bytes + file.size, 0);
        feedback.textContent =
            error ||
            (files.length
                ? `${files.length} file(s) selected · ${formatSize(total)} / ${formatSize(maxBytes)}.`
                : 'No file selected.');
        feedback.classList.toggle('is-error', Boolean(error));
        input.setCustomValidity(error);
        input.setAttribute('aria-invalid', String(Boolean(error)));
        buttons.forEach((button) => {
            button.disabled = Boolean(error) || alreadyDisabled.has(button);
        });
        return !error;
    };

    input.addEventListener('change', validate);
    form.addEventListener('submit', (event) => {
        if (!validate()) {
            event.preventDefault();
            input.focus();
            input.reportValidity();
        }
    });
    form.addEventListener('reset', () => queueMicrotask(validate));
    validate();
}

if (typeof document !== 'undefined') {
    document
        .querySelectorAll('form[data-media-upload]')
        .forEach(attachUploadLimit);
}
