<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Timeline</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="topbar">
    <a href="{{ route('timeline') }}" class="brand">TIMELINE<span>CURATOR</span></a>
    <div class="identity"><span>{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Sign out</button></form></div>
</header>
<div class="shell">
    <aside class="control-panel">
        <p class="eyebrow">CURATION POLICY</p>
        <h1>Train your task</h1>
        @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
        <section>
            <h2>Topics <span>{{ $topics->where('active', true)->count() }}/5</span></h2>
            @foreach($topics as $topic)<div class="policy-item"><strong>{{ $topic->name }}</strong><p>{{ $topic->brief }}</p></div>@endforeach
            <form method="post" action="{{ route('topics.store') }}" class="stack">@csrf
                <input name="name" placeholder="Topic name" required maxlength="100">
                <textarea name="brief" placeholder="What high-signal coverage looks like" required maxlength="3000"></textarea>
                <button class="button">Add topic</button>
            </form>
        </section>
        <section>
            <h2>Agent directives</h2>
            @foreach($directives as $directive)<div class="policy-item"><span class="pill {{ $directive->strength }}">{{ $directive->strength }}</span><p>{{ $directive->body }}</p></div>@endforeach
            <form method="post" action="{{ route('directives.store') }}" class="stack">@csrf
                <textarea name="body" placeholder="Tell the task what to prefer or avoid" required maxlength="3000"></textarea>
                <div class="split"><select name="strength"><option value="soft">Soft preference</option><option value="hard">Hard rule</option></select><input name="expires_at" type="date"></div>
                <input name="blocked_domains" placeholder="Blocked domains, comma-separated">
                <button class="button secondary">Add directive</button>
            </form>
        </section>
    </aside>
    <main class="feed">
        <div class="feed-heading"><div><p class="eyebrow">LATEST RESEARCH</p><h1>Your private signal</h1></div><p>{{ $stories->total() }} evidence-backed clusters</p></div>
        @forelse($stories as $story)
            <article class="story-card">
                <div class="story-meta"><time>{{ optional($story->published_at)->diffForHumans() }}</time><span>{{ $story->sources->count() }} sources</span></div>
                <h2>{{ $story->title }}</h2>
                <ul>@foreach($story->technical_bullets as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
                @if($story->why_it_matters)<p class="why"><strong>Why it matters</strong> {{ $story->why_it_matters }}</p>@endif
                <div class="sources">@foreach($story->sources as $source)<a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->role === 'primary' ? 'Primary' : 'Support' }} · {{ $source->domain }}</a>@endforeach</div>
                <form method="post" action="{{ route('feedback.store', $story) }}" class="feedback">@csrf
                    <label>Relevance <input type="range" name="relevance_score" min="1" max="5" value="{{ $story->feedback?->relevance_score ?? 3 }}"></label>
                    <label>Depth <input type="range" name="depth_score" min="1" max="5" value="{{ $story->feedback?->depth_score ?? 3 }}"></label>
                    <div class="chips">@foreach($semanticTags as $tag)<label><input type="checkbox" name="semantic_tags[]" value="{{ $tag }}" @checked(in_array($tag, $story->feedback?->semantic_tags ?? []))><span>{{ $tag }}</span></label>@endforeach</div>
                    <textarea name="comment" placeholder="Optional directive about this result">{{ $story->feedback?->comment }}</textarea>
                    <button class="button compact">Save feedback</button>
                </form>
            </article>
        @empty
            <section class="empty"><p class="eyebrow">BLANK SLATE</p><h2>Your task has not published a story yet.</h2><p>Add a topic, install the Timeline Curator plugin, then schedule your personal Codex task. Precision comes first, so empty runs are valid.</p></section>
        @endforelse
        {{ $stories->links() }}
    </main>
</div>
</body>
</html>
