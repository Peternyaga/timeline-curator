@extends('layouts.public')

@section('title', $snapshot['title'].' | Timeline Curator')

@section('social-meta')
    <link rel="canonical" href="{{ $presentation['url'] }}">
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Timeline Curator">
    <meta property="og:title" content="{{ $snapshot['title'] }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $presentation['url'] }}">
    <meta property="og:image" content="{{ $defaultSocialImage }}">
    <meta property="og:image:secure_url" content="{{ $defaultSocialImage }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Timeline Curator editorial research preview">
    @if($storySocialImage)
        <meta property="og:image" content="{{ $storySocialImage }}">
        <meta property="og:image:secure_url" content="{{ $storySocialImage }}">
        <meta property="og:image:alt" content="{{ $storySocialImageAlt }}">
    @endif
    @if($snapshot['published_at'] ?? null)
        <meta property="article:published_time" content="{{ $snapshot['published_at'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $snapshot['title'] }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $defaultSocialImage }}">
    <meta name="twitter:image:alt" content="Timeline Curator editorial research preview">
@endsection

@section('content')
<main class="public-story-shell">
    <article class="public-story">
        <header class="public-story-heading">
            <div>
                <p class="eyebrow">CURATED STORY</p>
                <h1>{{ $snapshot['title'] }}</h1>
            </div>
            @if($snapshot['published_at'] ?? null)
                <time datetime="{{ $snapshot['published_at'] }}">{{ \Illuminate\Support\Carbon::parse($snapshot['published_at'])->format('M j, Y') }}</time>
            @endif
        </header>

        @if(! empty($snapshot['media']))
            <div class="story-media public-story-media" aria-label="Story media">
                @include('partials.story-media-item', ['media' => (object) $snapshot['media'][0], 'hero' => true, 'priority' => true])
                @if(count($snapshot['media']) > 1)
                    <div class="story-media-gallery">
                        @foreach(array_slice($snapshot['media'], 1) as $media)
                            @include('partials.story-media-item', ['media' => (object) $media, 'hero' => false, 'priority' => false])
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <ul class="summary-points public-summary">
            @foreach($snapshot['summary_points'] as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>

        @if($snapshot['why_it_matters'] ?? null)
            <p class="why public-why"><strong>Why it matters</strong> {{ $snapshot['why_it_matters'] }}</p>
        @endif

        @if(! empty($snapshot['sources']))
            <section class="public-sources" aria-labelledby="public-sources-heading">
                <p class="eyebrow">INSPECTED EVIDENCE</p>
                <h2 id="public-sources-heading">Sources</h2>
                <div>
                    @foreach($snapshot['sources'] as $source)
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">
                            <span>{{ $source['role'] === 'primary' ? 'Primary' : 'Support' }}</span>
                            <strong>{{ $source['title'] }}</strong>
                            <small>{{ $source['domain'] }}</small>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section
            class="public-share-panel"
            aria-labelledby="share-this-story"
            data-public-share
            data-share-title="{{ $presentation['title'] }}"
            data-share-url="{{ $presentation['url'] }}"
            data-share-short-text="{{ $presentation['short_text'] }}"
            data-share-full-text="{{ $presentation['full_text'] }}"
        >
            <p class="eyebrow">PASS IT ON</p>
            <h2 id="share-this-story">Share this story</h2>
            <button type="button" class="button share-native" data-public-share-native>
                Share via device
                <small>Instagram and other installed apps</small>
            </button>
            <p class="share-instagram-guidance" data-public-instagram-guidance></p>
            <div class="share-platforms">
                @foreach($presentation['platforms'] as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" data-share-platform="{{ $platform }}">
                        {{ match($platform) {
                            'x' => 'X',
                            'linkedin' => 'LinkedIn',
                            'bluesky' => 'Bluesky',
                            default => ucfirst($platform),
                        } }}
                    </a>
                @endforeach
            </div>
            <button type="button" class="button secondary compact" data-public-share-copy>Copy prepared post</button>
            <p class="share-status" data-public-share-status aria-live="polite"></p>
        </section>
    </article>

    <aside class="public-story-cta">
        <p class="eyebrow">CREATE YOUR OWN TIMELINE</p>
        <h2>Follow the topics you choose.</h2>
        <p>Set the coverage and let your Codex curator return verified stories with useful context.</p>
        <a class="button" href="{{ route('register') }}">Create your Timeline</a>
    </aside>
</main>
@endsection
