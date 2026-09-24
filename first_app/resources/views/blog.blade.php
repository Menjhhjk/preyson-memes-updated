<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PreySON | Meme Vault</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #ffffff;
            color: #3b0764;
        }

        header {
            background: #ffffff;
            border-bottom: 3px solid #facc15;
            padding: 0.9rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(59, 7, 100, 0.06);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #facc15;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 900;
            color: #4c1d95;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .view-switcher {
            display: flex;
            background: #faf5ff;
            border: 2px solid #e9d5ff;
            border-radius: 10px;
            padding: 2px;
        }

        .view-btn {
            background: transparent;
            border: none;
            padding: 0.45rem 0.9rem;
            font-weight: 800;
            font-size: 0.85rem;
            color: #6b21a8;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .view-btn.active {
            background: #facc15;
            color: #3b0764;
            box-shadow: 0 2px 5px rgba(250, 204, 21, 0.4);
        }

        .admin-link {
            font-size: 0.85rem;
            font-weight: 800;
            color: #3b0764;
            text-decoration: none;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
            background: #facc15;
            border: 2px solid #eab308;
            box-shadow: 0 3px 0 #ca8a04;
        }

        /* Continuous Feed Layout */
        .feed-mode {
            max-width: 680px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .feed-mode .meme-card {
            background: #ffffff;
            border: 2px solid #f3e8ff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(59, 7, 100, 0.08);
        }

        .feed-mode .meme-header {
            padding: 1.25rem 1.5rem;
        }

        .feed-mode .meme-header h2 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #3b0764;
            word-break: break-word;
        }

        .feed-mode .media-box {
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            max-height: 600px;
        }

        .feed-mode .media-box img, 
        .feed-mode .media-box video {
            width: 100%;
            max-height: 600px;
            object-fit: contain;
        }

        .feed-mode .meme-footer {
            padding: 0.85rem 1.5rem;
            background: #faf5ff;
            font-size: 0.85rem;
            font-weight: 700;
            color: #6b21a8;
            display: flex;
            justify-content: space-between;
        }

        /* TikTok-Style Snap Scroll */
        .tiktok-mode {
            height: calc(100vh - 65px);
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            background: #000000;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tiktok-mode .meme-card {
            scroll-snap-align: start;
            scroll-snap-stop: always;
            height: calc(100vh - 65px);
            width: 100%;
            max-width: 500px;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #000;
        }

        .tiktok-mode .media-box img,
        .tiktok-mode .media-box video {
            width: 100%;
            height: 100%;
            max-height: calc(100vh - 65px);
            object-fit: contain;
        }

        .tiktok-mode .meme-header {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 2rem 1.5rem 1.5rem;
            background: linear-gradient(transparent, rgba(0,0,0,0.85));
            color: #fff;
            z-index: 10;
        }

        .tiktok-mode .meme-header h2 {
            color: #facc15;
            font-size: 1.15rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        .tiktok-mode .meme-footer {
            display: none;
        }

        /* Grid Mode */
        .grid-mode {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .grid-mode .meme-card {
            border: 2px solid #f3e8ff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(59, 7, 100, 0.05);
        }

        .grid-mode .media-box {
            height: 250px;
            background: #000;
        }

        .grid-mode .media-box img,
        .grid-mode .media-box video {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .grid-mode .meme-header {
            padding: 0.9rem;
        }

        .grid-mode .meme-header h2 {
            font-size: 1rem;
            color: #3b0764;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .grid-mode .meme-footer {
            padding: 0.6rem 0.9rem;
            background: #faf5ff;
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b21a8;
            display: flex;
            justify-content: space-between;
        }

        .badge {
            background: #facc15;
            color: #3b0764;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 800;
        }
    </style>
</head>
<body>

    <header>
        <a href="/" class="brand-container">
            <img src="{{ asset('logo.png') }}" alt="PreySON Logo" class="brand-logo">
            <span class="brand-name">PreySON</span>
        </a>

        <div class="header-controls">
            <div class="view-switcher">
                <button class="view-btn active" onclick="switchView('feed')">Feed</button>
                <button class="view-btn" onclick="switchView('tiktok')">⚡ Snap</button>
                <button class="view-btn" onclick="switchView('grid')">Grid</button>
            </div>

            @auth
                <a href="/dashboard" class="admin-link">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="admin-link">Sign In</a>
                <a href="{{ route('register') }}" class="admin-link">Sign Up</a>
            @endauth
        </div>
    </header>

    <main id="memeContainer" class="feed-mode">
        @forelse ($posts as $post)
            <article class="meme-card">
                <div class="meme-header">
                    <h2>{{ $post->title }}</h2>
                </div>

                <div class="media-box">
                    @if ($post->media_type === 'video')
                        <video src="{{ asset('storage/' . $post->media_path) }}" controls loop playsinline></video>
                    @else
                        <img src="{{ asset('storage/' . $post->media_path) }}" alt="{{ $post->title }}" loading="lazy">
                    @endif
                </div>

                <div class="meme-footer">
                    <span>Posted {{ $post->created_at->diffForHumans() }}</span>
                    <span class="badge">{{ strtoupper($post->media_type) }}</span>
                </div>
            </article>
        @empty
            <div style="text-align: center; padding: 4rem; color: #6b21a8; font-weight: 700;">
                No memes found. Upload some in the Admin Dashboard!
            </div>
        @endforelse
    </main>

    <script>
        function switchView(mode) {
            const container = document.getElementById('memeContainer');
            const buttons = document.querySelectorAll('.view-btn');

            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (mode === 'feed') {
                container.className = 'feed-mode';
                document.body.style.overflow = 'auto';
            } else if (mode === 'tiktok') {
                container.className = 'tiktok-mode';
                document.body.style.overflow = 'hidden';
            } else if (mode === 'grid') {
                container.className = 'grid-mode';
                document.body.style.overflow = 'auto';
            }
        }
    </script>

</body>
</html>
