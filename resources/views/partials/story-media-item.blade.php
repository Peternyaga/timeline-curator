<figure @class(['media-item', 'is-hero' => $hero ?? false])>
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
