<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PreySON - Sign In</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            padding: 1.5rem 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #faf7ff;
            color: #1e1b4b;
        }

        .login-card {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(109, 40, 217, 0.12);
            border: 1px solid #ede9fe;
            width: 100%;
            max-width: 440px;
            text-align: center;
        }

        .logo-wrap {
            margin-bottom: 1rem;
        }

        .logo-wrap img {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #7c3aed;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }

        .login-card h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #4c1d95;
            margin-bottom: 0.3rem;
        }

        .login-card p {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 1.75rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #f87171;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            text-align: left;
        }

        .alert-error ul {
            list-style: disc;
            padding-left: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4c1d95;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1.5px solid #ddd6fe;
            background: #faf7ff;
            color: #1e1b4b;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            margin-top: 0.5rem;
            border: none;
            border-radius: 8px;
            background: #7c3aed;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-submit:hover {
            background: #6d28d9;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .footer-text {
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .footer-text a {
            color: #7c3aed;
            font-weight: 600;
            text-decoration: none;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-wrap">
            <img src="{{ asset('logo.png') }}" alt="PreySON Logo">
        </div>

        <h2>Welcome to PreySON</h2>
        <p>Sign in to share and manage your memes</p>

        @if ($errors->any())
            <div class="alert-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('login.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="email">Email or Username</label>
                <input 
                    type="text"
                    id="email" 
                    name="email" 
                    value="{{ old('email') }}" 
                    placeholder="Email address or username"
                    autocomplete="username"
                    required 
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    autocomplete="current-password"
                    placeholder="••••••••" 
                    required
                >
            </div>

            @include('partials.color-captcha')

            <button type="submit" class="btn-submit">Sign In</button>
        </form>

        <div class="footer-text">
            Don't have an account? <a href="{{ route('register') }}">Sign Up</a>
        </div>
        <div class="footer-text">
            <a href="/">&larr; Back to PreySON</a>
        </div>
    </div>

</body>
</html>
