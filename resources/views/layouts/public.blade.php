<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Timeline Curator')</title>
    @yield('social-meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-share-page">
    <header class="public-topbar">
        <a href="{{ route('home') }}" class="brand">TIMELINE<span>CURATOR</span></a>
        <a class="button compact" href="{{ route('register') }}">Build your timeline</a>
    </header>

    @yield('content')
</body>
</html>
