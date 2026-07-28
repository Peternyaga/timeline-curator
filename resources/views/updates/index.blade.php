@extends('layouts.app')

@section('title', 'Timeline Updates')
@section('body-class', 'updates-page')

@section('content')
<main class="settings-shell">
    <header class="settings-heading">
        <div>
            <p class="eyebrow">WHAT'S NEW</p>
            <h1>Timeline updates</h1>
            <p>Product, curator, and connection changes that may affect your feed.</p>
        </div>
        @if($updates->where('read', false)->isNotEmpty())
            <form method="post" action="{{ route('updates.read-all') }}">
                @csrf
                <button class="button secondary compact" type="submit">Mark all as read</button>
            </form>
        @endif
    </header>

    @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif

    <div class="update-list">
        @foreach($updates as $update)
            <article class="update-card @if(!$update['read']) is-unread @endif">
                <div class="update-card-meta">
                    <span>Version {{ $update['version'] }}</span>
                    <time datetime="{{ $update['published_at'] }}">{{ \Illuminate\Support\Carbon::parse($update['published_at'])->format('M j, Y') }}</time>
                    @if(!$update['read'])<span class="unread-label">New</span>@endif
                </div>
                <h2>{{ $update['title'] }}</h2>
                <p>{{ $update['summary'] }}</p>
                <div class="update-card-actions">
                    <a class="text-link" href="{{ route($update['action_route']) }}">{{ $update['action_label'] }}</a>
                    @if(!$update['read'])
                        <form method="post" action="{{ route('updates.read', $update['id']) }}">
                            @csrf
                            <button class="text-button" type="submit">Mark as read</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <section class="updater-help">
        <p class="eyebrow">PLUGIN UPDATES</p>
        <h2>Keep Timeline Curator current</h2>
        <p>Refresh the Vumbua Labs marketplace and reinstall Timeline Curator. A new Codex task will load the new version.</p>
        <pre><code>codex plugin marketplace upgrade vumbua-labs
codex plugin add timeline-curator@vumbua-labs</code></pre>
    </section>
</main>
@endsection
