import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    attachUploadLimit,
    checkUploadSelection,
} from '../../public/upload-limits.js';

const mb = 1024 * 1024;
const limit = 100 * mb;
const file = (size, name = 'recording.mkv') => ({ name, size });

await test('4 GB selections are rejected using metadata without reading a file', () => {
    const error = checkUploadSelection([file(4 * 1024 * mb)], limit, 6);
    assert.match(error, /recording\.mkv is 4 GB/);
    assert.match(error, /100 MB per file/);
});

await test('the exact 100 MB boundary is accepted and one extra byte is rejected', () => {
    assert.equal(checkUploadSelection([file(limit)], limit, 6), '');
    assert.notEqual(checkUploadSelection([file(limit + 1)], limit, 6), '');
});

await test('the combined limit and six file count also apply', () => {
    assert.match(
        checkUploadSelection([file(60 * mb), file(50 * mb)], limit, 6),
        /combined limit is 100 MB/,
    );
    assert.equal(
        checkUploadSelection([file(60 * mb), file(40 * mb)], limit, 6),
        '',
    );
    assert.match(
        checkUploadSelection(
            Array.from({ length: 7 }, () => file(mb)),
            limit,
            6,
        ),
        /up to 6 files/,
    );
    assert.equal(checkUploadSelection([], limit, 1), '');
});

function uploadForm() {
    const input = new EventTarget();
    input.files = [];
    input.dataset = { maxUploadBytes: String(limit), maxFiles: '6' };
    input.setCustomValidity = (error) => {
        input.error = error;
    };
    input.setAttribute = (key, value) => {
        input[key] = value;
    };
    input.focus = () => {
        input.focused = true;
    };
    input.reportValidity = () => !input.error;
    const feedback = { textContent: '', classList: { toggle() {} } };
    const button = { disabled: false };
    const form = new EventTarget();
    form.querySelector = (selector) =>
        selector === '[data-media-input]' ? input : feedback;
    form.querySelectorAll = () => [button];
    attachUploadLimit(form);
    return { form, input, feedback, button };
}

await test('selecting an oversized file blocks publishing and choosing a smaller file restores it', () => {
    const { form, input, feedback, button } = uploadForm();
    input.files = [file(4 * 1024 * mb, '<huge recording>.mkv')];
    input.dispatchEvent(new Event('change'));
    assert.equal(button.disabled, true);
    assert.equal(input['aria-invalid'], 'true');
    assert.match(feedback.textContent, /<huge recording>\.mkv is 4 GB/);
    const rejected = new Event('submit', { cancelable: true });
    form.dispatchEvent(rejected);
    assert.equal(rejected.defaultPrevented, true);

    input.files = [file(20 * mb)];
    input.dispatchEvent(new Event('change'));
    assert.equal(button.disabled, false);
    assert.equal(input.error, '');
    assert.equal(input['aria-invalid'], 'false');
    assert.match(feedback.textContent, /20 MB \/ 100 MB/);
    const accepted = new Event('submit', { cancelable: true });
    form.dispatchEvent(accepted);
    assert.equal(accepted.defaultPrevented, false);
});

await test('submit rechecks changed files even without a selection event', () => {
    const { form, input, button } = uploadForm();
    input.files = [file(limit + 1)];
    const submit = new Event('submit', { cancelable: true });
    form.dispatchEvent(submit);
    assert.equal(submit.defaultPrevented, true);
    assert.equal(button.disabled, true);
});

await test('clearing a rejected optional replacement permits keeping the original file', () => {
    const { input, button, feedback } = uploadForm();
    input.files = [file(4 * 1024 * mb)];
    input.dispatchEvent(new Event('change'));
    input.files = [];
    input.dispatchEvent(new Event('change'));
    assert.equal(input.error, '');
    assert.equal(button.disabled, false);
    assert.equal(feedback.textContent, 'No file selected.');
});
