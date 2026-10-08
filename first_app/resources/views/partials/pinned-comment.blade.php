<aside class="pinned-comment" aria-label="Pinned comment">
    <strong>⌖ Pinned by the post owner</strong>
    <p class="plain-text">{{ $post->pinnedComment->body }}</p>
    <div class="form-actions"><a href="{{ route('comments.show', $post->pinnedComment) }}">{{ $post->pinnedComment->user->username }} · View comment →</a>
    @if(auth()->id() === $post->user_id)<form method="POST" action="{{ route('comments.pin', $post->pinnedComment) }}">@csrf @method('PUT')<input type="hidden" name="pinned" value="0"><button class="text-button" type="submit">Unpin</button></form>@endif</div>
</aside>
