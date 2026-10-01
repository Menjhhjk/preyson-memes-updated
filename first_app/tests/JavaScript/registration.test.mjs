import assert from 'node:assert/strict';
import { test } from 'node:test';
import { setTimeout as wait } from 'node:timers/promises';
import {
    registrationChecks,
    availabilityCheck,
    attachRegistration,
} from '../../public/registration.js';

const values = (password = '', confirmation = '') => ({
    username: '',
    email: '',
    password,
    password_confirmation: confirmation,
});

await test('password requirements and character counts update independently', () => {
    const weak = registrationChecks(values('abc'));
    assert.equal(weak.passwordCount, 3);
    assert.equal(weak['password-length'], false);
    assert.equal(weak['password-uppercase'], false);
    assert.equal(weak['password-number'], false);
    assert.equal(weak['password-special'], false);
    const strong = registrationChecks(values('Password1!'));
    for (const key of ['length', 'uppercase', 'number', 'special'])
        assert.equal(strong[`password-${key}`], true);
    assert.equal(
        registrationChecks(values('Password1 '))['password-special'],
        false,
    );
    assert.equal(
        registrationChecks(values('PASSWORD1!'))['password-uppercase'],
        true,
    );
});

await test('Unicode character counts match server validation', () => {
    const checks = registrationChecks(values('Ábcde1!😊'));
    assert.equal(checks.passwordCount, 8);
    assert.equal(checks['password-length'], true);
    assert.equal(checks['password-uppercase'], true);
});

await test('username requirements accept six characters and reject spaces and overlong values', () => {
    const check = (username) => registrationChecks({ ...values(), username });
    assert.equal(check('abcde')['username-length'], false);
    assert.equal(check('abcde').usernameCount, 5);
    assert.equal(check('abcdef')['username-length'], true);
    assert.equal(check('meme.user-1')['username-format'], true);
    assert.equal(check('two words')['username-format'], false);
    assert.equal(check('a'.repeat(51))['username-length'], false);
});

await test('confirmation follows changes to either password field', () => {
    assert.equal(
        registrationChecks(values('Password1!', 'Password1!'))[
            'password-match'
        ],
        true,
    );
    assert.equal(
        registrationChecks(values('Changed1!', 'Password1!'))['password-match'],
        false,
    );
    assert.equal(registrationChecks(values('', ''))['password-match'], false);
});

await test('availability checks debounce rapid typing and only send the requested account field', async () => {
    const requests = [];
    const results = [];
    const check = availabilityCheck('username', {
        url: '/registration/check',
        token: 'test-csrf',
        delay: 0,
        onResult: (result) => results.push(result),
        fetcher: async (url, options) => {
            requests.push({ url, options });
            return {
                ok: true,
                json: async () => ({
                    valid: true,
                    format_valid: true,
                    messages: [],
                }),
            };
        },
    });
    check('first_value');
    check('latest_value');
    await wait(10);
    assert.equal(requests.length, 1);
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        field: 'username',
        value: 'latest_value',
    });
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(results.at(-1).status, 'valid');
});

await test('an old availability response cannot overwrite the latest result', async () => {
    const pending = [];
    const results = [];
    const check = availabilityCheck('email', {
        url: '/registration/check',
        token: 'test',
        delay: 0,
        onResult: (result) => results.push(result),
        fetcher: () => new Promise((resolve) => pending.push(resolve)),
    });
    check('old@fictional.invalid');
    await wait(5);
    check('new@fictional.invalid');
    await wait(5);
    pending[1]({
        ok: true,
        json: async () => ({ valid: true, format_valid: true, messages: [] }),
    });
    await wait(0);
    pending[0]({
        ok: true,
        json: async () => ({ valid: false, messages: ['Already taken.'] }),
    });
    await wait(0);
    assert.equal(results.at(-1).status, 'valid');
    check(null);
    assert.equal(results.at(-1).status, 'idle');
});

await test('unavailable live checks are not presented as invalid account details', async () => {
    const results = [];
    const check = availabilityCheck('email', {
        url: '/registration/check',
        token: 'test',
        delay: 0,
        onResult: (result) => results.push(result),
        fetcher: async () => ({ ok: false, status: 429 }),
    });
    check('demo@fictional.invalid');
    await wait(5);
    assert.equal(results.at(-1).status, 'unavailable');
});

function node() {
    const value = new EventTarget();
    value.textContent = '';
    value.classes = new Set();
    value.attributes = new Map();
    value.classList = {
        toggle: (name, enabled) =>
            enabled ? value.classes.add(name) : value.classes.delete(name),
    };
    value.setAttribute = (name, text) => value.attributes.set(name, text);
    value.getAttribute = (name) => value.attributes.get(name);
    return value;
}

function registrationForm() {
    const form = node();
    form.dataset = { passwordMin: '8', feedbackUrl: '/registration/check' };
    const map = new Map();
    const fields = {};
    for (const name of [
        'username',
        'email',
        'password',
        'password_confirmation',
        'terms',
        '_token',
    ]) {
        const input = node();
        input.name = name;
        input.value = '';
        input.type =
            name === 'terms'
                ? 'checkbox'
                : name.startsWith('password')
                  ? 'password'
                  : 'text';
        input.checked = false;
        fields[name] = input;
        map.set(`[name="${name}"]`, input);
        map.set(`[data-feedback="${name}"]`, node());
        map.set(`[data-count="${name}"]`, node());
    }
    const rules = {};
    for (const key of [
        'username-length',
        'username-format',
        'username-available',
        'email-format',
        'email-available',
        'password-length',
        'password-uppercase',
        'password-number',
        'password-special',
        'password-match',
    ]) {
        const row = node();
        const icon = node();
        const state = node();
        row.querySelector = (selector) =>
            selector === '.requirement-icon' ? icon : state;
        rules[key] = row;
        map.set(`[data-rule="${key}"]`, row);
    }
    form.querySelector = (selector) => map.get(selector) ?? null;
    form.querySelectorAll = () => [];
    attachRegistration(form);
    return { fields, rules, map };
}

await test('typing changes invalid outlines, counters, and completed requirement styles live', () => {
    const { fields, rules, map } = registrationForm();
    fields.password.value = 'short';
    fields.password.dispatchEvent(new Event('input'));
    assert.equal(fields.password.getAttribute('aria-invalid'), 'true');
    assert.equal(
        map.get('[data-count="password"]').textContent,
        '5 characters',
    );
    assert.equal(rules['password-uppercase'].classes.has('is-unmet'), true);
    fields.password.value = 'Password1!';
    fields.password.dispatchEvent(new Event('input'));
    assert.equal(fields.password.getAttribute('aria-invalid'), 'false');
    for (const key of ['length', 'uppercase', 'number', 'special'])
        assert.equal(rules[`password-${key}`].classes.has('is-met'), true);
    fields.password_confirmation.value = 'Password1!';
    fields.password_confirmation.dispatchEvent(new Event('input'));
    assert.equal(rules['password-match'].classes.has('is-met'), true);
    fields.password.value = 'Different1!';
    fields.password.dispatchEvent(new Event('input'));
    assert.equal(
        fields.password_confirmation.getAttribute('aria-invalid'),
        'true',
    );
    assert.equal(rules['password-match'].classes.has('is-met'), false);
});

await test('server email syntax validation can confirm an unusual valid email address', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({ valid: true, format_valid: true, messages: [] }),
    });
    try {
        const { fields, rules } = registrationForm();
        fields.email.value = '"demo user"@fictional.invalid';
        fields.email.dispatchEvent(new Event('input'));
        await wait(550);
        assert.equal(fields.email.getAttribute('aria-invalid'), 'false');
        assert.equal(rules['email-format'].classes.has('is-met'), true);
        assert.equal(rules['email-available'].classes.has('is-met'), true);
    } finally {
        globalThis.fetch = originalFetch;
    }
});
