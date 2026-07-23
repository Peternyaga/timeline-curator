<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timeline Curator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing">
    <main class="hero">
        <p class="eyebrow">YOUR SIGNAL. YOUR POLICY. YOUR TASK.</p>
        <h1>A research timeline that learns only from you.</h1>
        <p class="lede">Timeline stores your topics and feedback. Your independently authenticated Codex task researches the web, filters evidence, and returns a private feed—without a shared scraping worker or application-side LLM calls.</p>
        <div class="hero-actions">
            @auth<a class="button" href="{{ route('timeline') }}">Open timeline</a>@else<a class="button" href="{{ route('register') }}">Create your Timeline</a><a class="text-link" href="{{ route('login') }}">Sign in</a>@endauth
            <a class="text-link" href="/.well-known/oauth-protected-resource">OAuth metadata →</a>
        </div>
        <section class="principles">
            <article><span>01</span><h2>Strictly isolated</h2><p>Tenant identity comes from the OAuth token, never a request field.</p></article>
            <article><span>02</span><h2>User-owned automation</h2><p>Each user installs, authorizes, and schedules their own Codex task.</p></article>
            <article><span>03</span><h2>Evidence first</h2><p>Every accepted story has three technical bullets and mapped citations.</p></article>
        </section>
    </main>
</body>
</html>
