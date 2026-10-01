<div class="video-playback" data-video-playback>
    <video controls playsinline preload="metadata" aria-label="{{ $post->title }}" src="{{ route('posts.media', $post) }}">
        Your browser cannot play this video. Use the download link below.
    </video>
    <div class="video-help">
        <p data-video-error role="status" hidden>This video could not play here. Try downloading it and opening it in a video player.</p>
        <a href="{{ route('posts.media', ['post' => $post, 'download' => 1]) }}" download>Download original video</a>
    </div>
</div>
