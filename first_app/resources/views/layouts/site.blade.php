<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Posts') · PreySON</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    <link rel="stylesheet" href="{{ asset('preyson.css') }}">
    <link rel="stylesheet" href="{{ asset('community.css') }}">
    <link rel="stylesheet" href="{{ asset('engagement.css') }}">
    <script type="module" src="{{ asset('engagement.js') }}"></script>
    <script src="{{ asset('preyson.js') }}" defer></script>
    <script type="module" src="{{ asset('community.js') }}"></script>
    <script type="module" src="{{ asset('upload-limits.js') }}"></script>
    @stack('head')
    @stack('styles')
</head>
<body @if($profileBackground ?? null) class="custom-profile-background" style="--profile-background: {{ $profileBackground }}" @endif>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-shell">
    <aside class="sidebar" aria-label="Main navigation">
        <a class="brand" href="{{ route('home') }}"><img src="{{ asset('logo.png') }}" alt="" width="42" height="42"><span>PreySON<span class="brand-sub">a little less serious.</span></span></a>
        <span class="nav-caption">YOUR DAILY DOSE</span>
        <nav class="side-nav">
            <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])><span aria-hidden="true">▦</span> Posts</a>
            @auth
                <a href="{{ route('notifications.index') }}" @class(['active' => request()->routeIs('notifications.*', 'warnings.mine')])><span aria-hidden="true">&#9679;</span> Notifications <span class="notification-count" data-notification-count data-count-url="{{ route('notifications.count') }}" @if(!$unreadNotifications) hidden @endif>{{ $unreadNotifications }}</span></a>
                <a href="{{ route('rewards.index') }}" @class(['active' => request()->routeIs('rewards.*')])><span aria-hidden="true">&#10022;</span> Rewards & charges</a>
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard', 'posts.edit')])><span aria-hidden="true">＋</span> {{ auth()->user()->canModerate() ? 'Manage posts' : 'My posts' }}</a>
                <a href="{{ route('profiles.show', auth()->user()) }}" @class(['active' => request()->routeIs('profile.*') || (request()->routeIs('profiles.show') && request()->route('member')?->id === auth()->id())])><span aria-hidden="true">◎</span> My profile</a>
            @endauth
            <a href="{{ route('premium.index') }}" @class(['active' => request()->routeIs('premium.*')])><span aria-hidden="true">✦</span> Premium & donate</a>
            @if(auth()->check() && auth()->user()->canModerate())
                <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])><span aria-hidden="true">&#9873;</span> Report logs</a>
            @endif
            @if(auth()->check() && auth()->user()->isAdmin())
                <span class="nav-caption">ADMINISTRATION</span>
                <a href="{{ route('corners.review') }}" @class(['active' => request()->routeIs('corners.*')])>Corner requests</a>
                <a href="{{ route('super-catalog.index') }}" @class(['active' => request()->routeIs('super-catalog.*')])>Super-reaction library</a>
                <a href="{{ route('accounts.index') }}" @class(['active' => request()->routeIs('accounts.*')])><span aria-hidden="true">♙</span> Account management</a>
            @endif
        </nav>
        <div class="sidebar-note"><span class="sparkle">✦</span><strong>Big meme energy.</strong><p>More posts. More reactions.<br>A little extra sparkle.</p><a href="{{ route('premium.index') }}">Explore Premium <span aria-hidden="true">↗</span></a></div>
        <div class="sidebar-bottom">
            @auth
                <a class="profile-chip" href="{{ route('profiles.show', auth()->user()) }}"><img class="avatar" src="{{ auth()->user()->avatarUrl() }}" alt=""><span><strong>{{ auth()->user()->username }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : (auth()->user()->role === 'moderator' ? 'Moderator' : (auth()->user()->hasPremium() ? 'Premium member' : 'Member')) }}</small></span></a>
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
@auth @include('partials.power-overlay') @endauth
@stack('scripts')
</body>
</html>
