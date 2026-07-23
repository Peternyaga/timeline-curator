@php
    $catalogId = $kind.'-preset-catalog';
    $categories = collect($presets)->pluck('category')->unique()->values();
@endphp

<section
    class="preset-catalog"
    aria-labelledby="{{ $catalogId }}-heading"
    data-preset-catalog
    data-preset-kind="{{ $kind }}"
>
    <div class="preset-heading">
        <div>
            <p class="eyebrow">QUICK START</p>
            <h3 id="{{ $catalogId }}-heading">{{ $kind === 'topic' ? 'Browse popular topics' : 'Choose a useful directive' }}</h3>
        </div>
        <span>{{ count($presets) }} presets</span>
    </div>

    <label class="preset-search">
        <span>Search presets</span>
        <input
            type="search"
            placeholder="{{ $kind === 'topic' ? 'Try “climate”, “music”, or “local”…' : 'Try “sources”, “recent”, or “depth”…' }}"
            autocomplete="off"
            data-preset-search
        >
    </label>

    <div class="preset-categories" role="group" aria-label="{{ ucfirst($kind) }} preset categories">
        <button type="button" class="preset-filter is-active" data-preset-category="all" aria-pressed="true">All</button>
        @foreach($categories as $category)
            <button type="button" class="preset-filter" data-preset-category="{{ $category }}" aria-pressed="false">{{ $category }}</button>
        @endforeach
    </div>

    <div class="preset-grid" data-preset-grid>
        @foreach($presets as $preset)
            @php
                $searchText = implode(' ', [
                    $preset['category'],
                    $preset['label'] ?? $preset['name'] ?? '',
                    $preset['body'] ?? $preset['brief'] ?? '',
                    ...($preset['keywords'] ?? []),
                ]);
            @endphp
            <article
                class="preset-card"
                data-preset-card
                data-preset-category-name="{{ $preset['category'] }}"
                data-preset-search-text="{{ $searchText }}"
            >
                <div class="preset-card-meta">
                    <span>{{ $preset['category'] }}</span>
                    @if($kind === 'directive')
                        <span class="pill {{ $preset['strength'] }}">{{ $preset['strength'] }}</span>
                    @endif
                </div>
                <h4>{{ $preset['label'] ?? $preset['name'] }}</h4>
                <p>{{ $preset['body'] ?? $preset['brief'] }}</p>
                <button
                    type="button"
                    class="preset-use"
                    data-preset-select
                    data-preset-id="{{ $preset['id'] }}"
                    @if($kind === 'topic')
                        data-preset-name="{{ $preset['name'] }}"
                        data-preset-brief="{{ $preset['brief'] }}"
                    @else
                        data-preset-body="{{ $preset['body'] }}"
                        data-preset-strength="{{ $preset['strength'] }}"
                    @endif
                    aria-pressed="false"
                >Use this {{ $kind }}</button>
            </article>
        @endforeach
    </div>

    <div class="preset-empty" data-preset-empty hidden>
        <p>No presets match that search.</p>
        <button type="button" class="text-button" data-preset-clear>Clear filters</button>
    </div>
    <p class="preset-status" data-preset-status aria-live="polite"></p>
    <div class="preset-divider"><span>Or customize your own</span></div>
</section>
