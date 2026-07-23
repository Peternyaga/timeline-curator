<article class="story-card" id="story-{{ $story->id }}" data-story-id="{{ $story->id }}">
    <div class="story-meta">
        <time datetime="{{ $story->published_at?->toIso8601String() }}">{{ optional($story->published_at)->diffForHumans() }}</time>
        <span>{{ $story->sources->count() }} sources</span>
    </div>
    <h2>{{ $story->title }}</h2>
    @if($story->media->isNotEmpty())
        <div class="story-media" aria-label="Story media">
            @foreach($story->media as $media)
                <figure class="media-item">
                    @if($media->media_type === 'image')
                        <a href="{{ $media->source_url }}" target="_blank" rel="noopener noreferrer">
                            <img
                                src="{{ $media->url }}"
                                alt="{{ $media->alt_text }}"
                                loading="lazy"
                                decoding="async"
                                referrerpolicy="no-referrer"
                                data-media-asset
                            >
                        </a>
                    @elseif($media->provider === 'direct')
                        <video
                            controls
                            preload="metadata"
                            @if($media->thumbnail_url) poster="{{ $media->thumbnail_url }}" @endif
                            aria-label="{{ $media->alt_text }}"
                            referrerpolicy="no-referrer"
                            data-media-asset
                        >
                            <source src="{{ $media->url }}">
                            <a href="{{ $media->source_url }}" target="_blank" rel="noopener noreferrer">View the video at its source</a>
                        </video>
                    @else
                        <div
                            class="video-consent"
                            data-video-provider="{{ $media->provider }}"
                            data-video-id="{{ $media->provider_id }}"
                            data-video-title="{{ $media->alt_text }}"
                        >
                            @if($media->thumbnail_url)
                                <img
                                    src="{{ $media->thumbnail_url }}"
                                    alt="{{ $media->alt_text }}"
                                    loading="lazy"
                                    decoding="async"
                                    referrerpolicy="no-referrer"
                                    data-media-asset
                                >
                            @endif
                            <button type="button" class="video-load" data-load-video>
                                Load video from {{ $media->provider === 'youtube' ? 'YouTube' : 'Vimeo' }}
                            </button>
                        </div>
                    @endif
                    <a
                        class="media-fallback"
                        href="{{ $media->source_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-media-fallback
                        hidden
                    >View media at its source</a>
                    <figcaption>
                        {{ $media->caption }}
                        <a href="{{ $media->source_url }}" target="_blank" rel="noopener noreferrer">Credit: {{ $media->credit }}</a>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
    <ul class="summary-points">
        @foreach(($story->summary_points ?: $story->technical_bullets) as $point)<li>{{ $point }}</li>@endforeach
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
            @if(! empty($story->feedback_tags))
                <div class="chips" aria-label="Feedback tags">
                    @foreach($story->feedback_tags as $tag)
                        <label>
                            <input
                                type="checkbox"
                                name="semantic_tags[]"
                                value="{{ $tag['id'] }}"
                                @checked(in_array($tag['id'], $story->feedback?->semantic_tags ?? []))
                            >
                            <span>{{ $tag['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            <textarea name="comment" placeholder="Optional directive about this result">{{ $story->feedback?->comment }}</textarea>
            <div class="form-actions">
                <button class="button compact" type="submit">Save feedback</button>
                <p class="form-status" data-form-status aria-live="polite"></p>
            </div>
        </form>
    </details>
</article>
