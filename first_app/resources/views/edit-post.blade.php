<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Edit Post - PreySON</title>
   <style>
       body {
           background-color: #ffffff;
           color: #3b0764;
           font-family: Arial, sans-serif;
           padding: 2rem;
       }
       .container {
           max-width: 500px;
           margin: 0 auto;
           border: 2px solid #e9d5ff;
           border-radius: 12px;
           padding: 2rem;
           box-shadow: 0 4px 12px rgba(59, 7, 100, 0.05);
       }
       .form-group {
           margin-bottom: 1.25rem;
       }
       label {
           display: block;
           font-weight: bold;
           margin-bottom: 0.5rem;
       }
       input[type="text"], input[type="file"] {
           width: 100%;
           padding: 0.65rem;
           box-sizing: border-box;
           border: 1.5px solid #d8b4fe;
           border-radius: 6px;
       }
       .preview {
           max-width: 100%;
           height: 200px;
           object-fit: contain;
           margin-bottom: 1rem;
           background: #000;
           display: block;
       }
       .btn-yellow {
           background: #facc15;
           color: #3b0764;
           border: 2px solid #eab308;
           padding: 0.75rem 1.5rem;
           font-weight: bold;
           border-radius: 6px;
           cursor: pointer;
       }
       .cancel-link {
           margin-left: 1rem;
           color: #7c3aed;
           text-decoration: none;
           font-weight: bold;
       }
   </style>
</head>
<body>

<div class="container">
   <h2>Edit Meme Post</h2>

   @if ($errors->any())
       <div role="alert" style="color: #991b1b; margin-bottom: 1rem;">
           @foreach ($errors->all() as $error)
               <p>{{ $error }}</p>
           @endforeach
       </div>
   @endif
   <form action="{{ route('posts.update', $post->id) }}" method="POST" enctype="multipart/form-data">
       @csrf
       @method('PUT')

       <div class="form-group">
           <label>Current Media</label>
           @if($post->media_type === 'video')
               <video src="{{ asset('storage/' . $post->media_path) }}" class="preview" controls></video>
           @else
               <img src="{{ asset('storage/' . $post->media_path) }}" class="preview" alt="Current Media">
           @endif
       </div>

       <div class="form-group">
           <label for="title">Title / Caption</label>
           <input type="text" id="title" name="title" maxlength="255" value="{{ old('title', $post->title) }}" required>
       </div>

       <div class="form-group">
           <label for="media">Replace File (Optional)</label>
           <input type="file" id="media" name="media" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov">
       </div>

       <p>Maximum replacement file size: 100 MB.</p>
       <button type="submit" class="btn-yellow">Save Changes</button>
       <a href="/dashboard" class="cancel-link">Cancel</a>
   </form>
</div>

</body>
</html>
