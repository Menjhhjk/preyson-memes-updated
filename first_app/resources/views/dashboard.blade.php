<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PreySON - Dashboard</title>
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

        nav {
            background: #ffffff;
            border-bottom: 3px solid #facc15;
            padding: 0.9rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(59, 7, 100, 0.06);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #facc15;
        }

        .brand-title {
            font-size: 1.35rem;
            font-weight: 900;
            color: #3b0764;
        }

        .nav-actions {
            display: flex;
            gap: 1.25rem;
            align-items: center;
        }

        .btn-live {
            color: #581c87;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .btn-logout {
            background: #faf5ff;
            color: #7e22ce;
            border: 2px solid #d8b4fe;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .container {
            max-width: 960px;
            margin: 2.5rem auto;
            padding: 0 1.5rem;
        }

        .alert-success {
            background: #fef08a;
            border: 2px solid #eab308;
            color: #713f12;
            padding: 0.9rem 1.25rem;
            border-radius: 12px;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .upload-card {
            background: #ffffff;
            border: 2px solid #f3e8ff;
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(59, 7, 100, 0.06);
            margin-bottom: 3rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 700;
            color: #4c1d95;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            border: 2px solid #e9d5ff;
            background: #faf5ff;
            color: #3b0764;
            font-size: 1rem;
            outline: none;
        }

        .btn-yellow {
            background: #facc15;
            color: #3b0764;
            border: 2px solid #eab308;
            box-shadow: 0 4px 0 #ca8a04;
            padding: 0.85rem 2rem;
            font-size: 1rem;
            font-weight: 800;
            border-radius: 10px;
            cursor: pointer;
        }

        .batch-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #faf5ff;
            border: 2px solid #f3e8ff;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 800;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-danger:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .grid-history {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
        }

        .history-card {
            border: 2px solid #f3e8ff;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            position: relative;
            box-shadow: 0 4px 10px rgba(59, 7, 100, 0.04);
        }

        .history-card input[type="checkbox"] {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 22px;
            height: 22px;
            accent-color: #7c3aed;
            cursor: pointer;
            z-index: 5;
        }

        .history-card img, .history-card video {
            width: 100%;
            height: 140px;
            object-fit: cover;
            display: block;
        }

        .history-card p {
            padding: 0.6rem;
            font-weight: 700;
            font-size: 0.85rem;
            color: #3b0764;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>

    <nav>
        <div class="brand-container">
            <img src="{{ asset('logo.png') }}" alt="PreySON Logo" class="brand-logo">
            <span class="brand-title">PreySON Studio</span>
        </div>
        <div class="nav-actions">
            <a href="/" class="btn-live" target="_blank">View Public Feed &nearr;</a>
            <form action="/logout" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </nav>

    <div class="container">
        <h1 style="font-size: 2.2rem; font-weight: 900; margin-bottom: 1.5rem;">{{ auth()->user()->is_admin ? 'Manage Vault' : 'My Memes' }}</h1>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" style="background: #fef2f2; border: 2px solid #ef4444; color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem;">
                <p><strong>The request could not be completed:</strong></p>
                <ul style="padding-left: 1.5rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="upload-card">
            <h2 style="margin-bottom: 1rem; font-weight: 800;">Upload Memes</h2>
            <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="title">Title / Caption (Optional)</label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Auto-generated from filename if empty">
                </div>
                <div class="form-group">
                    <label for="media">Select up to 6 images, GIFs, or videos</label>
                    <input type="file" id="media" name="media[]" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov{{ auth()->user()->is_admin ? ',.zip' : '' }}" multiple required aria-describedby="upload-help">
                    <p id="upload-help" style="margin-top: 0.5rem;">Up to 6 files, 100 MB total. Each file becomes a separate post.
                        @if (auth()->user()->is_admin) You can also select one ZIP archive for a batch import. @endif
                    </p>
                </div>
                <button type="submit" class="btn-yellow">🚀 Upload to Vault</button>
            </form>
        </div>

        <form id="batchDeleteForm" action="{{ route('posts.batchDelete') }}" method="POST">
            @csrf
            @method('DELETE')

            <div class="batch-bar">
                <div>
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="width: 18px; height: 18px; accent-color: #7c3aed; vertical-align: middle;">
                    <label for="selectAll" style="font-weight: 800; margin-left: 0.5rem; cursor: pointer;">Select All (<span id="selectedCount">0</span> selected)</label>
                </div>
                <button type="submit" id="deleteBtn" class="btn-danger" disabled onclick="return confirm('Are you sure you want to delete the selected memes?')">
                    🗑️ Delete Selected
                </button>
            </div>

            <div class="grid-history">
                @foreach($posts as $post)
                    <div class="history-card">
                        <input type="checkbox" name="post_ids[]" value="{{ $post->id }}" class="post-checkbox" onchange="updateCount()">
                        @if($post->media_type === 'video')
                            <video src="{{ asset('storage/' . $post->media_path) }}"></video>
                        @else
                            <img src="{{ asset('storage/' . $post->media_path) }}" alt="{{ $post->title }}">
                        @endif
                        <p title="{{ $post->title }}">{{ $post->title }}</p>
                        <a href="{{ route('posts.edit', $post) }}" style="display: block; padding: 0.6rem; color: #7c3aed; font-weight: 700;">Edit<span style="position: absolute; width: 1px; height: 1px; overflow: hidden;"> {{ $post->title }}</span></a>
                    </div>
                @endforeach
            </div>
        </form>
    </div>

    <script>
        document.getElementById('media').addEventListener('change', function () {
            const files = Array.from(this.files);
            const total = files.reduce((sum, file) => sum + file.size, 0);
            const hasZip = files.some(file => file.name.toLowerCase().endsWith('.zip'));
            this.setCustomValidity(files.length > 6 ? 'Select up to 6 files.' :
                hasZip && files.length > 1 ? 'Upload one ZIP archive separately.' :
                total > 100 * 1024 * 1024 ? 'Selected files must total 100 MB or less.' : '');
            this.reportValidity();
        });

        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.post-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateCount();
        }

        function updateCount() {
            const checked = document.querySelectorAll('.post-checkbox:checked').length;
            document.getElementById('selectedCount').innerText = checked;
            document.getElementById('deleteBtn').disabled = checked === 0;
        }
    </script>

</body>
</html>
