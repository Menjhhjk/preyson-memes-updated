<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Posts') · PreySON</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    <link rel="stylesheet" href="{{ asset('preyson.css') }}">
    <script src="{{ asset('preyson.js') }}" defer></script>
    @stack('head')
    @stack('styles')
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-shell">
    <aside class="sidebar" aria-label="Main navigation">
        <a class="brand" href="{{ route('home') }}"><img src="{{ asset('logo.png') }}" alt="" width="42" height="42"><span>PreySON<span class="brand-sub">a little less serious.</span></span></a>
        <span class="nav-caption">YOUR DAILY DOSE</span>
        <nav class="side-nav">
            <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])><span aria-hidden="true">▦</span> Posts</a>
            @auth
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard', 'posts.edit')])><span aria-hidden="true">＋</span> {{ auth()->user()->canModerate() ? 'Manage posts' : 'My posts' }}</a>
                <a href="{{ route('profile.edit') }}" @class(['active' => request()->routeIs('profile.*')])><span aria-hidden="true">◎</span> My profile</a>
            @endauth
            <a href="{{ route('premium.index') }}" @class(['active' => request()->routeIs('premium.*')])><span aria-hidden="true">✦</span> Premium & donate</a>
            @if(auth()->check() && auth()->user()->isAdmin())
                <span class="nav-caption">ADMINISTRATION</span>
                <a href="{{ route('accounts.index') }}" @class(['active' => request()->routeIs('accounts.*')])><span aria-hidden="true">♙</span> Account management</a>
            @endif
        </nav>
        <div class="sidebar-note"><span class="sparkle">✦</span><strong>Big meme energy.</strong><p>More posts. More reactions.<br>A little extra sparkle.</p><a href="{{ route('premium.index') }}">Explore Premium <span aria-hidden="true">↗</span></a></div>
        <div class="sidebar-bottom">
            @auth
                <a class="profile-chip" href="{{ route('profile.edit') }}"><img class="avatar" src="{{ auth()->user()->avatarUrl() }}" alt=""><span><strong>{{ auth()->user()->username }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->role === 'moderator' ? 'Moderator' : (auth()->user()->hasPremium() ? 'Premium member' : 'Member')) }}</small></span></a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="sign-out" type="submit">Sign out ↗</button></form>
            @else
                <a class="button full-width" href="{{ route('login') }}">Sign in</a>
                <p class="small muted">New here? <a href="{{ route('register') }}">Join the fun</a></p>
            @endauth
            <a class="small muted" href="{{ url('/policies') }}">Terms & privacy</a>
        </div>
    </aside>
    <div class="main-shell">
        <header class="topbar"><span class="topbar-label">THE INTERNET'S SILLY LITTLE CORNER</span><span class="demo-pill"><span aria-hidden="true">●</span> Just for fun · demo</span></header>
        <main id="main-content" class="main-content">
            @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
            @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert"><strong>The request could not be completed:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
        <footer class="site-footer">Made for a laugh. <span>PreySON © {{ date('Y') }}</span></footer>
    </div>
</div>
@stack('scripts')
</body>
</html>