export function registrationChecks(values, minimum = 8) {
    const username = values.username.trim().toLowerCase();
    const email = values.email.trim().toLowerCase();
    return {
        usernameCount: [...values.username].length,
        passwordCount: [...values.password].length,
        'username-length': username.length >= 6 && username.length <= 50,
        'username-format': /^[a-z0-9_.-]+$/i.test(username),
        'email-format':
            [...email].length <= 255 && /^[^\s@]+@[^\s@]+$/u.test(email),
        'password-length': [...values.password].length >= minimum,
        'password-uppercase': /\p{Lu}/u.test(values.password),
        'password-number': /\p{N}/u.test(values.password),
        'password-special': /[\p{P}\p{S}]/u.test(values.password),
        'password-match':
            values.password_confirmation.length > 0 &&
            values.password_confirmation === values.password,
    };
}

// Debounce requests and ignore stale responses, even if abort arrives too late.
export function availabilityCheck(
    field,
    { url, token, onResult, fetcher = globalThis.fetch, delay = 500 },
) {
    let timer;
    let controller;
    let revision = 0;
    return (value) => {
        const current = ++revision;
        clearTimeout(timer);
        controller?.abort();
        if (value === null) {
            onResult({ status: 'idle' });
            return;
        }
        controller = new AbortController();
        const signal = controller.signal;
        onResult({ status: 'pending' });
        timer = setTimeout(async () => {
            try {
                const response = await fetcher(url, {
                    method: 'POST',
                    signal,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ field, value }),
                });
                if (!response.ok) throw new Error('Check unavailable');
                const result = await response.json();
                if (typeof result.valid !== 'boolean')
                    throw new Error('Invalid response');
                if (current === revision) {
                    onResult({
                        status: result.valid ? 'valid' : 'invalid',
                        formatValid: result.format_valid,
                        message: (result.messages ?? []).join(' '),
                    });
                }
            } catch {
                if (current === revision) onResult({ status: 'unavailable' });
            }
        }, delay);
    };
}

export function attachRegistration(form) {
    const names = [
        'username',
        'email',
        'password',
        'password_confirmation',
        'terms',
    ];
    const inputs = Object.fromEntries(
        names.map((name) => [name, form.querySelector(`[name="${name}"]`)]),
    );
    const state = Object.fromEntries(
        names.map((name) => [
            name,
            {
                touched:
                    inputs[name].type === 'checkbox'
                        ? inputs[name].checked
                        : inputs[name].value !== '',
                serverError:
                    inputs[name].getAttribute('aria-invalid') === 'true',
                serverMessage: form.querySelector(`[data-feedback="${name}"]`)
                    .textContent,
            },
        ]),
    );
    const remote = { username: { status: 'idle' }, email: { status: 'idle' } };
    const values = () =>
        Object.fromEntries(names.map((name) => [name, inputs[name].value]));
    const checks = () =>
        registrationChecks(values(), Number(form.dataset.passwordMin));

    const rule = (key, met, touched, pending = false) => {
        const row = form.querySelector(`[data-rule="${key}"]`);
        row.classList.toggle('is-met', met);
        row.classList.toggle('is-unmet', !met && touched && !pending);
        row.querySelector('.requirement-icon').textContent = met
            ? '✓'
            : pending
              ? '…'
              : '○';
        row.querySelector('[data-rule-state]').textContent = met
            ? 'Met'
            : pending
              ? 'Checking'
              : 'Not met';
    };
    const fieldFeedback = (name, invalid, message, success = false) => {
        const feedback = form.querySelector(`[data-feedback="${name}"]`);
        inputs[name].setAttribute('aria-invalid', String(invalid));
        feedback.textContent = message;
        feedback.classList.toggle('is-error', invalid);
        feedback.classList.toggle('is-success', success && !invalid);
    };
    const render = (name) => {
        const result = checks();
        const touched = state[name].touched || state[name].serverError;
        if (name === 'username' || name === 'email') {
            const status = remote[name];
            const keys =
                name === 'username'
                    ? ['username-length', 'username-format']
                    : ['email-format'];
            if (name === 'email' && typeof status.formatValid === 'boolean')
                result['email-format'] = status.formatValid;
            keys.forEach((key) => rule(key, result[key], touched));
            rule(
                `${name}-available`,
                status.status === 'valid',
                status.status === 'invalid' && status.formatValid !== false,
                status.status === 'pending',
            );
            if (name === 'username')
                form.querySelector('[data-count="username"]').textContent =
                    `${result.usernameCount} / 50 characters`;
            const localValid = keys.every((key) => result[key]);
            const invalid =
                state[name].serverError ||
                (touched && (!localValid || status.status === 'invalid'));
            let message = '';
            if (state[name].serverError) message = state[name].serverMessage;
            else if (status.status === 'invalid') message = status.message;
            else if (touched && !localValid)
                message =
                    name === 'username'
                        ? 'Use 6–50 characters with letters, numbers, dots, underscores, or hyphens.'
                        : 'Enter an email address such as you@example.com (up to 255 characters).';
            else if (status.status === 'pending')
                message = 'Checking availability…';
            else if (status.status === 'valid')
                message = `${name === 'username' ? 'Username' : 'Email'} is available.`;
            else if (status.status === 'unavailable')
                message =
                    'Live check unavailable. This will be checked when you sign up.';
            fieldFeedback(name, invalid, message, status.status === 'valid');
        } else if (name === 'password') {
            const keys = [
                'password-length',
                'password-uppercase',
                'password-number',
                'password-special',
            ];
            keys.forEach((key) => rule(key, result[key], touched));
            form.querySelector('[data-count="password"]').textContent =
                `${result.passwordCount} characters`;
            const valid = keys.every((key) => result[key]);
            fieldFeedback(
                name,
                state[name].serverError || (touched && !valid),
                state[name].serverError
                    ? state[name].serverMessage
                    : valid
                      ? 'All password requirements met.'
                      : touched
                        ? 'Complete the password requirements.'
                        : '',
                valid,
            );
        } else if (name === 'password_confirmation') {
            const valid = result['password-match'];
            rule('password-match', valid, touched);
            fieldFeedback(
                name,
                state[name].serverError || (touched && !valid),
                state[name].serverError
                    ? state[name].serverMessage
                    : valid
                      ? 'Passwords match.'
                      : touched
                        ? 'Enter the same password in both fields.'
                        : '',
                valid,
            );
        } else {
            fieldFeedback(
                name,
                touched && !inputs.terms.checked,
                touched && !inputs.terms.checked
                    ? 'Agree to the demo terms and acknowledge the privacy notice to continue.'
                    : '',
            );
        }
    };
    const clearServerErrors = (name) => {
        state[name].serverError = false;
        form.querySelectorAll('[data-server-error-field]').forEach((item) => {
            if (item.dataset.serverErrorField === name) item.hidden = true;
        });
        const summary = form.querySelector('#registration-errors');
        if (summary)
            summary.hidden = ![...summary.querySelectorAll('li')].some(
                (item) => !item.hidden,
            );
    };
    const lookups = Object.fromEntries(
        ['username', 'email'].map((name) => [
            name,
            availabilityCheck(name, {
                url: form.dataset.feedbackUrl,
                token: form.querySelector('[name="_token"]').value,
                onResult: (result) => {
                    remote[name] = result;
                    render(name);
                },
            }),
        ]),
    );
    const scheduleLookup = (name) => {
        const result = checks();
        const valid =
            name === 'username'
                ? result['username-length'] && result['username-format']
                : inputs.email.value.trim().length > 0 &&
                  [...inputs.email.value.trim()].length <= 255;
        lookups[name](valid ? inputs[name].value.trim().toLowerCase() : null);
    };
    names.forEach((name) => {
        const update = () => {
            state[name].touched = true;
            clearServerErrors(name);
            if (lookups[name]) scheduleLookup(name);
            render(name);
            if (name === 'password') {
                clearServerErrors('password_confirmation');
                render('password_confirmation');
            }
        };
        inputs[name].addEventListener('input', update);
        inputs[name].addEventListener('change', update);
        inputs[name].addEventListener('blur', () => {
            state[name].touched = true;
            render(name);
        });
        render(name);
    });
    ['username', 'email'].forEach(scheduleLookup);
    // Keep the server's all-errors-at-once validation, including CAPTCHA.
    form.addEventListener('submit', () =>
        names.forEach((name) => {
            state[name].touched = true;
            render(name);
        }),
    );
    form.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = inputs[button.dataset.togglePassword];
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute(
                'aria-label',
                `${show ? 'Hide' : 'Show'} ${input.name === 'password' ? 'password' : 'password confirmation'}`,
            );
        });
    });
    form.querySelector('#registration-errors')?.focus();
}

if (typeof document !== 'undefined') {
    const form = document.querySelector('[data-registration]');
    if (form) attachRegistration(form);
}
