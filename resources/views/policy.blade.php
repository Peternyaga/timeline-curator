@extends('layouts.app')

@section('title', 'Curation Policy')
@section('body-class', 'policy-page')

@section('content')
<main class="policy-shell">
    <header class="policy-heading">
        <p class="eyebrow">CURATION POLICY</p>
        <h1>Train your task</h1>
        <p>Define the signal you want. Changes are picked up by the next curation run.</p>
    </header>

    @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
    @if($errors->any())
        <div class="flash error" role="alert">
            <strong>Please correct the following:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="policy-grid">
        <section class="policy-column" aria-labelledby="topics-heading">
            <div class="section-heading">
                <div><p class="eyebrow">DISCOVERY</p><h2 id="topics-heading">Topics</h2></div>
                <span>{{ $activeTopics->count() }}/5 active</span>
            </div>

            <details
                class="create-panel"
                data-create-panel="topic"
                data-initially-open="{{ ($activeTopics->isEmpty() || old('name') !== null || old('brief') !== null) ? 'true' : 'false' }}"
                @if($activeTopics->isEmpty() || old('name') !== null || old('brief') !== null) open @endif
            >
                <summary>Add a topic</summary>
                <form method="post" action="{{ route('topics.store') }}" class="stack" data-preset-form="topic">
                    @csrf
                    @include('partials.preset-catalog', ['kind' => 'topic', 'presets' => $topicPresets])
                    <label>Topic name<input name="name" value="{{ old('name') }}" required maxlength="100" data-preset-target="name"></label>
                    <label>Coverage brief<textarea name="brief" required maxlength="3000" placeholder="What high-signal coverage looks like" data-preset-target="brief">{{ old('brief') }}</textarea></label>
                    <button class="button" type="submit">Add topic</button>
                </form>
            </details>

            <div class="policy-list">
                @forelse($activeTopics as $topic)
                    <article class="policy-card" id="topic-{{ $topic->id }}">
                        <div class="policy-card-heading"><div><span class="state-dot"></span><strong>{{ $topic->name }}</strong></div><span class="status-label">Active</span></div>
                        <p>{{ $topic->brief }}</p>
                        <details class="inline-editor">
                            <summary>Edit topic</summary>
                            <form method="post" action="{{ route('topics.update', $topic) }}" class="stack">
                                @csrf @method('PATCH')
                                <label>Topic name<input name="name" value="{{ $topic->name }}" required maxlength="100"></label>
                                <label>Coverage brief<textarea name="brief" required maxlength="3000">{{ $topic->brief }}</textarea></label>
                                <button class="button compact" type="submit">Save changes</button>
                            </form>
                        </details>
                        <form method="post" action="{{ route('topics.archive', $topic) }}" class="archive-form">
                            @csrf @method('PATCH')
                            <button class="text-button" type="submit">Archive topic</button>
                        </form>
                    </article>
                @empty
                    <p class="policy-empty">No active topics yet.</p>
                @endforelse
            </div>

            @if($archivedTopics->isNotEmpty())
                <details class="archive-panel">
                    <summary>Archived topics <span>{{ $archivedTopics->count() }}</span></summary>
                    <div class="policy-list">
                        @foreach($archivedTopics as $topic)
                            <article class="policy-card is-archived" id="topic-{{ $topic->id }}">
                                <strong>{{ $topic->name }}</strong>
                                <p>{{ $topic->brief }}</p>
                                <details class="inline-editor">
                                    <summary>Edit topic</summary>
                                    <form method="post" action="{{ route('topics.update', $topic) }}" class="stack">
                                        @csrf @method('PATCH')
                                        <label>Topic name<input name="name" value="{{ $topic->name }}" required maxlength="100"></label>
                                        <label>Coverage brief<textarea name="brief" required maxlength="3000">{{ $topic->brief }}</textarea></label>
                                        <button class="button compact" type="submit">Save changes</button>
                                    </form>
                                </details>
                                <form method="post" action="{{ route('topics.restore', $topic) }}">
                                    @csrf @method('PATCH')
                                    <button class="button compact secondary" type="submit">Restore topic</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>

        <section class="policy-column" aria-labelledby="directives-heading">
            <div class="section-heading">
                <div><p class="eyebrow">BEHAVIOUR</p><h2 id="directives-heading">Agent directives</h2></div>
                <span>{{ $activeDirectives->count() }} active</span>
            </div>

            <details
                class="create-panel"
                data-create-panel="directive"
                data-initially-open="{{ ($activeDirectives->isEmpty() || old('body') !== null) ? 'true' : 'false' }}"
                @if($activeDirectives->isEmpty() || old('body') !== null) open @endif
            >
                <summary>Add a directive</summary>
                <form method="post" action="{{ route('directives.store') }}" class="stack" data-preset-form="directive">
                    @csrf
                    @include('partials.preset-catalog', ['kind' => 'directive', 'presets' => $directivePresets])
                    <label>Instruction<textarea name="body" required maxlength="3000" placeholder="Tell the task what to prefer or avoid" data-preset-target="body">{{ old('body') }}</textarea></label>
                    <div class="split">
                        <label>Strength<select name="strength" data-preset-target="strength"><option value="soft" @selected(old('strength', 'soft') === 'soft')>Soft preference</option><option value="hard" @selected(old('strength') === 'hard')>Hard rule</option></select></label>
                        <label>Expires<input name="expires_at" type="date" value="{{ old('expires_at') }}"></label>
                    </div>
                    <label>Blocked domains<input name="blocked_domains" value="{{ old('blocked_domains') }}" placeholder="example.com, spam.test"></label>
                    <button class="button secondary" type="submit">Add directive</button>
                </form>
            </details>

            <div class="policy-list">
                @forelse($activeDirectives as $directive)
                    <article class="policy-card" id="directive-{{ $directive->id }}">
                        <div class="policy-card-heading">
                            <span class="pill {{ $directive->strength }}">{{ $directive->strength }}</span>
                            @if($directive->expires_at)<span class="status-label">Until {{ $directive->expires_at->format('M j, Y') }}</span>@endif
                        </div>
                        <p>{{ $directive->body }}</p>
                        @if(data_get($directive->structured_rules, 'blocked_domains'))
                            <p class="structured-rule"><strong>Blocks:</strong> {{ implode(', ', $directive->structured_rules['blocked_domains']) }}</p>
                        @endif
                        <details class="inline-editor">
                            <summary>Edit directive</summary>
                            @include('partials.directive-form', ['directive' => $directive, 'action' => route('directives.update', $directive)])
                        </details>
                        <form method="post" action="{{ route('directives.archive', $directive) }}" class="archive-form">
                            @csrf @method('PATCH')
                            <button class="text-button" type="submit">Archive directive</button>
                        </form>
                    </article>
                @empty
                    <p class="policy-empty">No active directives yet.</p>
                @endforelse
            </div>

            @if($archivedDirectives->isNotEmpty())
                <details class="archive-panel">
                    <summary>Archived directives <span>{{ $archivedDirectives->count() }}</span></summary>
                    <div class="policy-list">
                        @foreach($archivedDirectives as $directive)
                            <article class="policy-card is-archived" id="directive-{{ $directive->id }}">
                                <div class="policy-card-heading"><span class="pill {{ $directive->strength }}">{{ $directive->strength }}</span><span class="status-label">Archived</span></div>
                                <p>{{ $directive->body }}</p>
                                <details class="inline-editor">
                                    <summary>Edit directive</summary>
                                    @include('partials.directive-form', ['directive' => $directive, 'action' => route('directives.update', $directive)])
                                </details>
                                <form method="post" action="{{ route('directives.restore', $directive) }}">
                                    @csrf @method('PATCH')
                                    <button class="button compact secondary" type="submit">Restore directive</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    </div>
</main>
@endsection
