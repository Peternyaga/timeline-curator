<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Timeline Curator')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body-class')">
<header class="topbar">
    <a href="{{ route('timeline') }}" class="brand">TIMELINE<span>CURATOR</span></a>
    <nav class="desktop-nav" aria-label="Primary">
        <a href="{{ route('timeline') }}" @if(request()->routeIs('timeline*')) aria-current="page" @endif>Feed</a>
        <a href="{{ route('policy') }}" @if(request()->routeIs('policy') || request()->routeIs('topics.*') || request()->routeIs('directives.*')) aria-current="page" @endif>Policy</a>
    </nav>
    <div class="identity">
        <span>{{ auth()->user()->name }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">Sign out</button></form>
    </div>
</header>

@yield('content')

<nav class="mobile-nav" aria-label="Primary">
    <a href="{{ route('timeline') }}" @if(request()->routeIs('timeline*')) aria-current="page" @endif>
        <span aria-hidden="true">◉</span> Feed
    </a>
    <a href="{{ route('policy') }}" @if(request()->routeIs('policy') || request()->routeIs('topics.*') || request()->routeIs('directives.*')) aria-current="page" @endif>
        <span aria-hidden="true">☷</span> Policy
    </a>
</nav>
</body>
</html>
