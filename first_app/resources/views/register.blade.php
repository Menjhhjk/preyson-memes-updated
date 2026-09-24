<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>PreySON - Sign Up</title>
   <style>
       * {
           box-sizing: border-box;
           margin: 0;
           padding: 0;
           font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
       }

       body {
           background-color: #ffffff;
           color: #3b0764;
           min-height: 100vh;
           display: flex;
           justify-content: center;
           align-items: center;
           padding: 1.5rem;
       }

       .signup-container {
           width: 100%;
           max-width: 440px;
           background: #ffffff;
           border: 2px solid #e9d5ff;
           border-radius: 16px;
           padding: 2.25rem 2rem;
           box-shadow: 0 10px 30px rgba(59, 7, 100, 0.08);
       }

       .header {
           text-align: center;
           margin-bottom: 1.75rem;
       }

       .header h1 {
           color: #3b0764;
           font-size: 1.85rem;
           font-weight: 900;
           letter-spacing: -0.5px;
       }

       .header p {
           color: #6b21a8;
           font-size: 0.95rem;
           margin-top: 0.35rem;
       }

       .form-row {
           display: flex;
           gap: 1rem;
       }

       .form-group {
           margin-bottom: 1.15rem;
           flex: 1;
       }

       label {
           display: block;
           margin-bottom: 0.4rem;
           font-size: 0.85rem;
           font-weight: 700;
           color: #4c1d95;
       }

       input[type="text"],
       input[type="email"],
       input[type="password"] {
           width: 100%;
           padding: 0.75rem 0.9rem;
           border: 2px solid #e9d5ff;
           border-radius: 8px;
           background: #faf5ff;
           color: #3b0764;
           font-size: 0.95rem;
           outline: none;
           transition: border-color 0.2s;
       }

       input:focus {
           border-color: #facc15;
           background: #ffffff;
       }

       .password-field {
           position: relative;
           display: flex;
           align-items: center;
       }

       .password-field input {
           padding-right: 2.75rem;
       }

       .toggle-btn {
           position: absolute;
           right: 0.5rem;
           background: none;
           border: none;
           color: #6b21a8;
           cursor: pointer;
           font-size: 1.1rem;
           padding: 0.25rem;
           display: flex;
           align-items: center;
           justify-content: center;
       }

       .toggle-btn:hover {
           color: #facc15;
       }

       .btn-submit {
           width: 100%;
           background-color: #facc15;
           color: #3b0764;
           border: 2px solid #eab308;
           box-shadow: 0 4px 0 #ca8a04;
           padding: 0.85rem;
           font-size: 1rem;
           font-weight: 800;
           border-radius: 10px;
           cursor: pointer;
           margin-top: 0.5rem;
           transition: transform 0.1s, box-shadow 0.1s;
       }

       .btn-submit:hover {
           transform: translateY(-2px);
           box-shadow: 0 6px 0 #ca8a04;
       }

       .btn-submit:active {
           transform: translateY(2px);
           box-shadow: 0 1px 0 #ca8a04;
       }

       .footer-text {
           text-align: center;
           margin-top: 1.5rem;
           font-size: 0.9rem;
           color: #6b21a8;
       }

       .footer-text a {
           color: #7c3aed;
           font-weight: 800;
           text-decoration: none;
       }

       .footer-text a:hover {
           text-decoration: underline;
       }
       .form-group { min-width: 0; }
       :focus-visible { outline: 3px solid #7c3aed; outline-offset: 3px; }
       @media (max-width: 480px) { .form-row { flex-direction: column; gap: 0; } .signup-container { padding: 1.5rem; } }
   </style>
</head>
<body>

   <div class="signup-container">
       <div class="header">
           <h1>Join PreySON</h1>
           <p>Create an account to post memes</p>
       </div>

       @if ($errors->any())
           <div role="alert" style="background: #fef2f2; color: #991b1b; padding: 1rem; margin-bottom: 1rem;">
               <ul style="padding-left: 1rem;">
                   @foreach ($errors->all() as $error)
                       <li>{{ $error }}</li>
                   @endforeach
               </ul>
           </div>
       @endif
       <form action="{{ route('register.store') }}" method="POST">
           @csrf
           <div class="form-row">
               <div class="form-group">
                   <label for="name">First Name</label>
                   <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="given-name" placeholder="John" required>
               </div>
               <div class="form-group">
                   <label for="surname">Surname</label>
                   <input type="text" id="surname" name="surname" value="{{ old('surname') }}" maxlength="255" autocomplete="family-name" placeholder="Doe" required>
               </div>
           </div>

           <div class="form-group">
               <label for="username">Username</label>
               <input type="text" id="username" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="username" placeholder="johndoe" pattern="[A-Za-z0-9_]+" aria-describedby="username-help" required>
           </div>

           <div class="form-group">
               <label for="email">Email Address</label>
               <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="john@example.com" required>
           </div>

           <div class="form-group">
               <label for="password">Password</label>
               <div class="password-field">
                   <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" placeholder="••••••••" required>
                   <button type="button" class="toggle-btn" onclick="toggleVisibility('password', this)" aria-label="Show password" aria-pressed="false">&#128065;</button>
               </div>
           </div>

           <div class="form-group">
               <label for="password_confirmation">Confirm Password</label>
               <div class="password-field">
                   <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="••••••••" required>
                   <button type="button" class="toggle-btn" onclick="toggleVisibility('password_confirmation', this)" aria-label="Show password" aria-pressed="false">&#128065;</button>
               </div>
           </div>

           <button type="submit" class="btn-submit">Sign Up</button>
       </form>

       <p id="username-help" style="margin-top: 1rem; font-size: 0.85rem;">Usernames use letters, numbers, and underscores. Passwords need at least 8 characters.</p>
       <div class="footer-text">
           Already have an account? <a href="{{ route('login') }}">Sign In</a>
       </div>
   </div>

   <script>
       function toggleVisibility(inputId, btn) {
           const input = document.getElementById(inputId);
           if (input.type === 'password') {
               input.type = 'text';
               btn.style.color = '#ca8a04';
               btn.setAttribute('aria-label', 'Hide password');
               btn.setAttribute('aria-pressed', 'true');
           } else {
               input.type = 'password';
               btn.style.color = '#6b21a8';
               btn.setAttribute('aria-label', 'Show password');
               btn.setAttribute('aria-pressed', 'false');
           }
       }
   </script>

</body>
</html>
