<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timeline Curator</title>
    @vite('resources/css/app.css')
</head>
<body class="landing">
    <main class="hero">
        <figure class="hero-artwork">
            <img
                src="{{ asset('images/timeline-home-hero-1400.jpg') }}"
                srcset="{{ asset('images/timeline-home-hero-720.jpg') }} 720w, {{ asset('images/timeline-home-hero-1400.jpg') }} 1400w"
                sizes="(max-width: 800px) calc(100vw - 32px), (max-width: 1240px) calc(100vw - 64px), 1176px"
                width="1400"
                height="735"
                alt="Timeline Curator presented as an editorial collage of stories, landscapes, and connected research."
                fetchpriority="high"
                decoding="async"
            >
        </figure>

        <section class="hero-intro" aria-labelledby="landing-title">
            <p class="eyebrow">YOUR SIGNAL. YOUR POLICY. YOUR TASK.</p>
            <h1 id="landing-title">A research timeline that learns only from you.</h1>
            <p class="lede">Timeline stores your topics and feedback. Your independently authenticated Codex task researches the web, filters evidence, and returns a private feed—without a shared scraping worker or application-side LLM calls.</p>
            <div class="hero-actions">
                @auth<a class="button" href="{{ route('timeline') }}">Open timeline</a>@else<a class="button" href="{{ route('register') }}">Create your Timeline</a><a class="text-link" href="{{ route('login') }}">Sign in</a>@endauth
                <a class="text-link" href="/.well-known/oauth-protected-resource">OAuth metadata →</a>
            </div>
        </section>

        <section class="principles">
            <article><span>01</span><h2>Strictly isolated</h2><p>Tenant identity comes from the OAuth token, never a request field.</p></article>
            <article><span>02</span><h2>User-owned automation</h2><p>Each user installs, authorizes, and schedules their own Codex task.</p></article>
            <article><span>03</span><h2>Evidence, made visual</h2><p>Focused summaries pair with inspected sources and verified, attributed story media whenever a suitable visual exists.</p></article>
        </section>
    </main>
</body>
</html>
