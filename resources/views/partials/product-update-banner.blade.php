@if($latestProductUpdate)
    <aside class="product-update-banner" role="status" aria-labelledby="product-update-title">
        <div>
            <p class="eyebrow">TIMELINE UPDATE · {{ $latestProductUpdate['version'] }}</p>
            <h2 id="product-update-title">{{ $latestProductUpdate['title'] }}</h2>
            <p>{{ $latestProductUpdate['summary'] }}</p>
        </div>
        <div class="product-update-actions">
            <a class="button compact" href="{{ route($latestProductUpdate['action_route']) }}">
                {{ $latestProductUpdate['action_label'] }}
            </a>
            <a class="text-link" href="{{ route('updates.index') }}">View all updates</a>
            <form method="post" action="{{ route('updates.read', $latestProductUpdate['id']) }}">
                @csrf
                <button class="text-button" type="submit">Dismiss</button>
            </form>
        </div>
    </aside>
@endif
