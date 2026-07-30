<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Install Timeline Curator in Codex, connect your account, run your first curation, and keep your feed updated automatically.">
    <title>Setup Guide — Timeline Curator</title>
    @vite('resources/css/app.css')
</head>
<body class="guide-page">
    <header class="guide-public-topbar">
        <a href="{{ route('home') }}" class="brand"><span>TIMELINE</span> CURATOR</a>
        <nav aria-label="Guide actions">
            <a class="text-link" href="{{ route('login') }}">Sign in</a>
            <a class="button compact" href="{{ route('register') }}">Create your Timeline</a>
        </nav>
    </header>

    @include('guide.content')
</body>
</html>
