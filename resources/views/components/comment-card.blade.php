<article class="comment-card">
    <div class="comment-avatar">
        @if($comment->user?->avatar_path)
            <img src="{{ asset('storage/'.$comment->user->avatar_path) }}" alt="{{ $comment->user->username }}">
        @else
            <span>{{ mb_substr($comment->user?->username ?: 'N', 0, 1) }}</span>
        @endif
    </div>
    <div class="comment-body">
        <div class="comment-head">
            <a href="{{ route('profile.show', $comment->user->username) }}">{{ '@'.$comment->user->username }}</a>
            <span>{{ $comment->created_at->diffForHumans() }}</span>
        </div>
        <p>{{ $comment->body }}</p>
        <div class="comment-actions">
            @auth
                <form method="post" action="{{ route('comments.vote', $comment) }}" aria-label="Yukarı oy">
                    @csrf
                    <input type="hidden" name="value" value="1">
                    <button class="vote-button" title="Yukarı oy"><i class="fa-solid fa-chevron-up"></i></button>
                </form>
                <strong class="comment-score {{ $comment->score > 0 ? 'positive' : ($comment->score < 0 ? 'negative' : '') }}">{{ $comment->score }}</strong>
                <form method="post" action="{{ route('comments.vote', $comment) }}" aria-label="Aşağı oy">
                    @csrf
                    <input type="hidden" name="value" value="-1">
                    <button class="vote-button" title="Aşağı oy"><i class="fa-solid fa-chevron-down"></i></button>
                </form>
                <form method="post" action="{{ route('comments.report', $comment) }}">
                    @csrf
                    <button class="comment-tool" title="Şikayet et"><i class="fa-regular fa-flag"></i></button>
                </form>
                @if(in_array(auth()->user()?->role, ['admin', 'super_admin'], true))
                    <form method="post" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('Bu yorum silinsin mi?')">
                        @csrf
                        @method('DELETE')
                        <button class="comment-tool danger" title="Yorumu sil"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                @endif
            @else
                <strong class="comment-score {{ $comment->score > 0 ? 'positive' : ($comment->score < 0 ? 'negative' : '') }}">{{ $comment->score }}</strong>
            @endauth
        </div>
        @auth
            <form class="reply-form" method="post" action="{{ route('media.comment', $media) }}">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <input name="body" maxlength="2000" placeholder="Cevap yaz..." required>
                <button title="Cevapla"><i class="fa-regular fa-paper-plane"></i></button>
            </form>
        @endauth
        @if($comment->replies->isNotEmpty())
            <div class="comment-replies">
                @foreach($comment->replies as $reply)
                    @include('components.comment-card', ['comment' => $reply, 'media' => $media])
                @endforeach
            </div>
        @endif
    </div>
</article>
