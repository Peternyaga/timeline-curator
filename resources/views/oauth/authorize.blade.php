<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize {{ $client->name }} — Timeline Curator</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="auth-page">
<main class="auth-card consent-card">
    <p class="eyebrow">AUTHORIZE TIMELINE CURATOR</p><h1>Connect {{ $client->name }}?</h1>
    <p>This grants the Codex task access to <strong>{{ auth()->user()->name }}’s</strong> Timeline account. Tenant identity is derived from this authorization and cannot be supplied by the task.</p>
    <ul class="scope-list"><li>Read your topics, directives, and feedback policy</li><li>Create and complete curation runs</li><li>Publish evidence-backed story clusters to your feed</li></ul>
    <form method="post" action="{{ route('oauth.authorize') }}">
        @csrf
        @foreach ($parameters as $name => $value)
            @if ($name !== 'scopes')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <div class="consent-actions"><button class="button" name="decision" value="approve" type="submit">Approve access</button><button class="link-button" name="decision" value="deny" type="submit">Cancel</button></div>
    </form>
    <p class="security-note">Authorization codes expire in five minutes and can be used only once. PKCE is required.</p>
</main>
</body></html>
