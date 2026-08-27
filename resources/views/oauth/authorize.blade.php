<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize {{ $client->name }} — Timeline Curator</title>@vite('resources/css/app.css')</head>
<body class="auth-page">
<main class="auth-card consent-card">
    <p class="eyebrow">AUTHORIZE TIMELINE CURATOR</p><h1>Connect {{ $client->name }}?</h1>
    <p>This grants the Codex task access to <strong>{{ auth()->user()->name }}’s</strong> Timeline account. Tenant identity is derived from this authorization and cannot be supplied by the task.</p>
    @php($requestedScopes = $parameters['scopes'] ?? [])
    <ul class="scope-list">
        <li>Read your topics, directives, and story feedback</li>
        <li>Create and complete curation runs</li>
        <li>Publish evidence-backed stories</li>
        @if(in_array('read:job-search-context', $requestedScopes, true))
            <li>Read non-sensitive job-search preferences and job feedback</li>
        @endif
        @if(in_array('write:job-batches', $requestedScopes, true))
            <li>Publish evidence-backed job matches</li>
        @endif
        @if(in_array('read:approved-applications', $requestedScopes, true))
            <li>Read an application profile only after you approve that specific job</li>
        @endif
        @if(in_array('write:application-progress', $requestedScopes, true))
            <li>Record application materials, questions, progress, and confirmation evidence</li>
        @endif
    </ul>
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
