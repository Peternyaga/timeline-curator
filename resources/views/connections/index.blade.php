@extends('layouts.app')

@section('title', 'Timeline Connections')
@section('body-class', 'connections-page')

@section('content')
<main class="settings-shell">
    <header class="settings-heading">
        <div>
            <p class="eyebrow">SECURITY</p>
            <h1>Connected curators</h1>
            <p>Timeline keeps each approved Codex connection active until you revoke it.</p>
        </div>
    </header>

    @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif

    <div class="connection-list">
        @forelse($grants as $grant)
            @php($lastUsed = $grant->accessTokens->pluck('last_used_at')->filter()->sortDesc()->first())
            <article class="connection-card">
                <div class="connection-heading">
                    <div>
                        <h2>{{ $grant->client?->name ?? 'Codex' }}</h2>
                        <p>Authorized {{ $grant->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="status-label @if($grant->revoked_at) is-revoked @endif">
                        {{ $grant->revoked_at ? 'Revoked' : 'Active' }}
                    </span>
                </div>
                <dl>
                    <div><dt>Last curator contact</dt><dd>{{ $lastUsed?->diffForHumans() ?? 'Not used yet' }}</dd></div>
                    <div><dt>Last token renewal</dt><dd>{{ $grant->last_refreshed_at?->diffForHumans() ?? 'Not renewed yet' }}</dd></div>
                </dl>
                @if(!$grant->revoked_at)
                    <form method="post" action="{{ route('connections.destroy', $grant) }}" data-revoke-connection>
                        @csrf @method('DELETE')
                        <button class="text-button danger-text" type="submit">Revoke this connection</button>
                    </form>
                @endif
            </article>
        @empty
            <section class="empty">
                <h2>No Codex connections yet.</h2>
                <p>Install Timeline Curator and approve its OAuth request to create one.</p>
            </section>
        @endforelse
    </div>
</main>
@endsection
