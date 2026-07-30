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
            <p class="eyebrow">YOUR TIMELINE</p>
            <h1>Stories selected for your topics</h1>
        </div>
        <p><span data-story-total>{{ $stories->total() }}</span> evidence-backed clusters</p>
    </header>

    @if(session('status'))<p class="flash page-flash">{{ session('status') }}</p>@endif
    @if($curatorHealth)
        <aside class="curator-health-warning" role="alert">
            <div>
                <strong>{{ $curatorHealth['title'] }}</strong>
                <p>{{ $curatorHealth['message'] }}</p>
            </div>
            <a class="button compact secondary" href="{{ route('connections.index') }}">Check connection</a>
        </aside>
    @endif

    <button class="new-stories-banner" type="button" data-new-stories hidden aria-live="polite"></button>

    <div id="story-list" data-story-list>
        @forelse($stories as $story)
            @include('partials.story-card', [
                'story' => $story,
                'priorityMedia' => $stories->currentPage() === 1 && $loop->first,
            ])
        @empty
            <section class="empty" data-empty-state>
                <p class="eyebrow">BLANK SLATE</p>
                <h2>Your task has not published a story yet.</h2>
                <p>Add a topic, install the Timeline Curator plugin, then schedule your personal Codex task. Precision comes first, so empty runs are valid.</p>
                <div class="empty-actions">
                    <a class="button" href="{{ route('policy') }}">Configure your policy</a>
                    <a class="text-link" href="{{ url('/guide') }}">Follow the setup guide</a>
                </div>
            </section>
        @endforelse
    </div>

    <div class="pagination">{{ $stories->links() }}</div>
</main>

@include('partials.share-dialog')
@endsection
