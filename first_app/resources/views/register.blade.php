<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PreySON ? Sign Up</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    <link rel="stylesheet" href="{{ asset('preyson.css') }}">
    <link rel="stylesheet" href="{{ asset('registration.css') }}">
    <script type="module" src="{{ asset('registration.js') }}"></script>
</head>
<body class="registration-page">
@php($passwordMinimum = \App\Rules\RegistrationPassword::MIN_LENGTH)
<main class="registration-card">
    <a class="registration-brand" href="{{ route('home') }}"><img src="{{ asset('logo.png') }}" alt="" width="40" height="40">PreySON</a>
    <header class="registration-heading"><span class="eyebrow">YOUR NEXT GOOD SCROLL STARTS HERE</span><h1>Join the fun.</h1><p>A few details, then you're part of the community.</p></header>
    <form action="{{ route('register.store') }}" method="POST" novalidate data-registration data-feedback-url="{{ route('registration.check') }}" data-password-min="{{ $passwordMinimum }}">
        @csrf
        @if ($errors->any())
            <div id="registration-errors" class="registration-errors" role="alert" tabindex="-1">
                <strong>Please fix the following errors:</strong>
                <ul>@foreach ($errors->getMessages() as $field => $messages) @foreach ($messages as $message)
                    <li data-server-error-field="{{ $field }}">{{ $message }}</li>
                @endforeach @endforeach</ul>
            </div>
        @endif

        <div class="registration-field" data-field="username">
            <div class="registration-label"><label for="username">Username</label><span class="character-count" id="username-count" data-count="username">0 / 50 characters</span></div>
            <input class="registration-input" type="text" id="username" name="username" value="{{ old('username') }}" minlength="6" maxlength="50" autocomplete="username" placeholder="meme_fan" required aria-describedby="username-count username-rules username-feedback" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}">
            @include('partials.registration-requirements', ['rulesId' => 'username-rules', 'requirements' => ['username-length' => '6?50 characters', 'username-format' => 'Letters, numbers, dots, underscores, or hyphens', 'username-available' => 'An available username']])
            <p class="registration-feedback" id="username-feedback" data-feedback="username" role="status">{{ $errors->first('username') }}</p>
        </div>

        <div class="registration-field" data-field="email">
            <div class="registration-label"><label for="email">Email address</label><span class="field-tag">Demo friendly</span></div>
            <input class="registration-input" type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="you@example.com" required aria-describedby="email-help email-rules email-feedback" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            <p class="registration-hint" id="email-help">A made-up email is fine. No inbox or verification needed.</p>
            @include('partials.registration-requirements', ['rulesId' => 'email-rules', 'requirements' => ['email-format' => 'An email address, such as you@example.com', 'email-available' => 'An email that is not already in use']])
            <p class="registration-feedback" id="email-feedback" data-feedback="email" role="status">{{ $errors->first('email') }}</p>
        </div>

        <div class="registration-field" data-field="password">
            <div class="registration-label"><label for="password">Password</label><span class="character-count" id="password-count" data-count="password">0 characters</span></div>
            <div class="registration-password">
                <input class="registration-input" type="password" id="password" name="password" minlength="{{ $passwordMinimum }}" autocomplete="new-password" placeholder="Make it a good one" required aria-describedby="password-count password-rules password-feedback" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
            @include('partials.registration-requirements', ['rulesId' => 'password-rules', 'extraClass' => 'password-requirements', 'requirements' => ['password-length' => 'At least '.$passwordMinimum.' characters', 'password-uppercase' => 'One capital letter', 'password-number' => 'One number', 'password-special' => 'One special character, e.g. ! @ #']])
            <p class="registration-feedback" id="password-feedback" data-feedback="password" role="status">{{ $errors->first('password') }}</p>
        </div>

        <div class="registration-field" data-field="password_confirmation">
            <div class="registration-label"><label for="password_confirmation">Confirm password</label></div>
            <div class="registration-password">
                <input class="registration-input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Once more, just to be sure" required aria-describedby="confirmation-rules password_confirmation-feedback" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}">
                <button type="button" class="password-toggle" data-toggle-password="password_confirmation" aria-label="Show password confirmation" aria-pressed="false">Show</button>
            </div>
            @include('partials.registration-requirements', ['rulesId' => 'confirmation-rules', 'requirements' => ['password-match' => 'Matches your password']])
            <p class="registration-feedback" id="password_confirmation-feedback" data-feedback="password_confirmation" role="status">{{ $errors->first('password_confirmation') }}</p>
        </div>

        @include('partials.color-captcha')

        <div class="registration-consent" data-field="terms">
            <label><input type="checkbox" id="terms" name="terms" value="1" required @checked(old('terms')) aria-describedby="terms-feedback" aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"><span>I agree to the <a href="{{ route('policies') }}#terms" target="_blank" rel="noopener">demo Terms of Service</a> and acknowledge the <a href="{{ route('policies') }}#privacy" target="_blank" rel="noopener">Privacy Notice</a> (opens a new tab).</span></label>
            <p class="registration-feedback" id="terms-feedback" data-feedback="terms" role="status">{{ $errors->first('terms') }}</p>
        </div>
        <button type="submit" class="registration-submit">Create my account <span aria-hidden="true">?</span></button>
    </form>
    <p class="registration-footer">Already part of the fun? <a href="{{ route('login') }}">Sign in</a></p>
</main>
</body>
</html>
