import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { webcrypto } from 'node:crypto';

const source = readFileSync(
    new URL('../../public/engagement.js', import.meta.url),
    'utf8',
);
function harness() {
    const state = {
        confirmed: true,
        calls: [],
        reply: { ok: true, balance: { total: 2 }, kind: 'boost' },
    };
    const feedback = { textContent: '' },
        button = { disabled: false },
        balance = { textContent: '3' },
        key = { value: webcrypto.randomUUID() };
    const badge = {
        dataset: { countUrl: '/notifications/count' },
        textContent: '0',
        hidden: true,
    };
    const form = {
        dataset: { powerForm: 'boost', powerConfirm: 'Spend one charge?' },
        action: '/posts/1/boost',
        addEventListener: (_, callback) => {
            state.submit = callback;
        },
        querySelector: (selector) => (selector === 'img' ? null : key),
        closest: (selector) =>
            selector === 'details' ? null : { querySelector: () => feedback },
    };
    const document = {
        hidden: false,
        querySelector: (selector) =>
            selector === '[data-notification-count]' ? badge : null,
        querySelectorAll: (selector) =>
            selector === '[data-power-form]'
                ? [form]
                : selector.includes('balance')
                  ? [balance]
                  : [button],
        addEventListener: (_, callback) => {
            state.visibility = callback;
        },
    };
    const fetch = async (url, options) => {
        state.calls.push({ url, options, key: key.value });
        if (state.fail) throw Error('Connection interrupted');
        return { ok: state.reply.ok, json: async () => state.reply };
    };
    runInNewContext(source, {
        document,
        fetch,
        crypto: webcrypto,
        FormData: class {},
        localStorage: { getItem: () => null },
        clearInterval() {},
        window: {
            fetch,
            confirm: () => state.confirmed,
            setInterval: (callback) => {
                state.poll = callback;
            },
            addEventListener() {},
        },
    });
    return {
        state,
        button,
        feedback,
        balance,
        key,
        badge,
        document,
        submit: () => state.submit({ preventDefault() {} }),
    };
}

await test('cancelling a power confirmation sends nothing and spends nothing', async () => {
    const h = harness();
    h.state.confirmed = false;
    await h.submit();
    assert.equal(h.state.calls.length, 0);
    assert.equal(h.button.disabled, false);
});

await test('uncertain power requests retain the retry key and success updates every charge display', async () => {
    const h = harness(),
        original = h.key.value;
    h.state.fail = true;
    await h.submit();
    assert.equal(h.key.value, original);
    assert.equal(h.button.disabled, false);
    assert.match(h.feedback.textContent, /interrupted/);
    h.state.fail = false;
    await h.submit();
    assert.equal(h.state.calls[1].key, original);
    assert.notEqual(h.key.value, original);
    assert.equal(h.balance.textContent, 2);
    assert.match(h.feedback.textContent, /24 hours/);
});

await test('exhausted charges disable use and validation errors leave controls usable', async () => {
    const h = harness();
    h.state.reply = { ok: false, errors: { charge: ['No charges left.'] } };
    await h.submit();
    assert.equal(h.feedback.textContent, 'No charges left.');
    assert.equal(h.button.disabled, false);
    h.state.reply = { ok: true, balance: { total: 0 }, kind: 'boost' };
    await h.submit();
    assert.equal(h.button.disabled, true);
    assert.equal(h.balance.textContent, 0);
});

await test('notification polling uses the unread endpoint and pauses in background tabs', async () => {
    const h = harness();
    h.state.reply = { ok: true, unread: 102 };
    await h.state.poll();
    assert.equal(h.state.calls[0].url, '/notifications/count');
    assert.equal(h.badge.textContent, '99+');
    assert.equal(h.badge.hidden, false);
    h.document.hidden = true;
    await h.state.poll();
    assert.equal(h.state.calls.length, 1);
    h.document.hidden = false;
    h.state.reply.unread = 0;
    await h.state.visibility();
    assert.equal(h.badge.hidden, true);
});
