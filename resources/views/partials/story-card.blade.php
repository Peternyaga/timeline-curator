<article class="story-card" id="story-{{ $story->id }}" data-story-id="{{ $story->id }}">
    <div class="story-meta">
        <time datetime="{{ $story->published_at?->toIso8601String() }}">{{ optional($story->published_at)->diffForHumans() }}</time>
        <span>{{ $story->sources->count() }} sources</span>
    </div>
    <h2>{{ $story->title }}</h2>
    <ul class="technical-bullets">
        @foreach($story->technical_bullets as $bullet)<li>{{ $bullet }}</li>@endforeach
    </ul>
    @if($story->why_it_matters)
        <p class="why"><strong>Why it matters</strong> {{ $story->why_it_matters }}</p>
    @endif
    <div class="sources">
        @foreach($story->sources as $source)
            <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->role === 'primary' ? 'Primary' : 'Support' }} &middot; {{ $source->domain }}</a>
        @endforeach
    </div>

    <details class="feedback-disclosure" @if($errors->has('feedback_'.$story->id)) open @endif>
        <summary>
            <span>{{ $story->feedback ? 'Update your feedback' : 'Rate this story' }}</span>
            @if($story->feedback)<span class="feedback-saved">Saved</span>@endif
        </summary>
        <form method="post" action="{{ route('feedback.store', $story) }}" class="feedback" data-feedback-form>
            @csrf
            <label>Relevance <input type="range" name="relevance_score" min="1" max="5" value="{{ $story->feedback?->relevance_score ?? 3 }}"></label>
            <label>Depth <input type="range" name="depth_score" min="1" max="5" value="{{ $story->feedback?->depth_score ?? 3 }}"></label>
            <div class="chips" aria-label="Feedback tags">
                @foreach($semanticTags as $tag)
                    <label><input type="checkbox" name="semantic_tags[]" value="{{ $tag }}" @checked(in_array($tag, $story->feedback?->semantic_tags ?? []))><span>{{ $tag }}</span></label>
                @endforeach
            </div>
            <textarea name="comment" placeholder="Optional directive about this result">{{ $story->feedback?->comment }}</textarea>
            <div class="form-actions">
                <button class="button compact" type="submit">Save feedback</button>
                <p class="form-status" data-form-status aria-live="polite"></p>
            </div>
        </form>
    </details>
</article>
