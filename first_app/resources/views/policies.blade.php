<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo terms & privacy · PreySON</title>
    <style>
        * { box-sizing: border-box; } body { margin: 0; padding: 2rem 1rem; font: 1rem/1.7 system-ui, sans-serif; color: #302640; background: #faf7ff; }
        main { max-width: 760px; margin: auto; background: #fff; border: 1px solid #e8def8; border-radius: 20px; padding: clamp(1.3rem, 5vw, 3rem); }
        h1,h2 { color: #4c1d95; line-height: 1.2; } h2 { margin-top: 2rem; } a { color: #6d28d9; } .notice { padding: 1rem; border-radius: 10px; background: #fef3c7; } :focus-visible { outline: 3px solid #7c3aed; outline-offset: 3px; }
    </style>
</head>
<body>
<main>
    <a href="{{ route('home') }}">← Back to PreySON</a>
    <h1>Demo terms & privacy</h1>
    <p>Version: October 1, 2026</p>
    <p class="notice">PreySON is a simulated meme community. Use made-up profile details and an email such as <strong>you@example.com</strong>. You do not need an accessible inbox. Do not enter real payment information or sensitive personal data.</p>
    <h2 id="terms">Community terms</h2>
    <ul>
        <li>Only upload content you have permission to share. Do not post harassment, threats, private information, malicious files, or unlawful content.</li>
        <li>Posts and their owner labels are visible to visitors. Choose a username and profile picture that you are comfortable showing publicly.</li>
        <li>Administrators manage accounts and posts. Moderators can manage posts to help keep the community usable.</li>
        <li>Premium and donations are demonstrations. No payment is collected, and no recurring charge or financial agreement is created. Simulated Premium lasts 30 days and its benefits expire automatically.</li>
        <li>This demo may be reset during development. Keep your own copies of anything you upload.</li>
    </ul>
    <h2 id="privacy">Privacy notice</h2>
    <p>PreySON stores your username, email, password hash, profile image, posts, reactions, simulated subscription information, and the time and version of your agreement to these terms. First names and surnames are not collected or stored. Your password is hashed, rather than stored as readable text.</p>
    <p>Session cookies are used for signing in and for the color challenge. The color check is a lightweight demo interaction, with optional color names for accessibility. Email is used as an account identifier; registration does not require email verification.</p>
    <p>Administrators can view and edit account details and remove accounts. To request correction or deletion of demo data, contact the person running this local PreySON installation. Do not use this demo as storage for confidential information.</p>
    <p>By selecting the registration checkbox, you agree to these demo community terms and acknowledge this privacy notice.</p>
    <a href="{{ route('register') }}">Go to registration</a>
</main>
</body>
</html>
