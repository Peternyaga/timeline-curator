@extends('layouts.app')

@section('title', 'Your Timeline')
@section('body-class', 'timeline-page')

@section('content')
<main
    class="feed"
    @if($liveCursor)
        data-live-feed
        data-updates-url="{{ route('timeline.updates') }}"
        data-after-published-at="{{ $liveCursor['published_at'] }}"
        data-after-id="{{ $liveCursor['id'] }}"
    @endif
>
    <header class="feed-heading">
        <div>
            <p class="eyebrow">LATEST RESEARCH</p>
            <h1>Your private signal</h1>
        </div>
        <p><span data-story-total>{{ $stories->total() }}</span> evidence-backed clusters</p>
    </header>

    @if(session('status'))<p class="flash page-flash">{{ session('status') }}</p>@endif

    <button class="new-stories-banner" type="button" data-new-stories hidden aria-live="polite"></button>

    <div id="story-list" data-story-list>
        @forelse($stories as $story)
            @include('partials.story-card', ['story' => $story])
        @empty
            <section class="empty" data-empty-state>
                <p class="eyebrow">BLANK SLATE</p>
                <h2>Your task has not published a story yet.</h2>
                <p>Add a topic, install the Timeline Curator plugin, then schedule your personal Codex task. Precision comes first, so empty runs are valid.</p>
                <a class="button" href="{{ route('policy') }}">Configure your policy</a>
            </section>
        @endforelse
    </div>

    <div class="pagination">{{ $stories->links() }}</div>
</main>
@endsection
