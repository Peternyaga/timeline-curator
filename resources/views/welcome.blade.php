<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Timeline is a private research feed built from the topics, sources, and editorial preferences you choose.">
    <title>Timeline Curator</title>
    @vite('resources/css/app.css')
</head>
<body class="landing">
    <header class="landing-topbar">
        <a class="landing-brand" href="{{ route('home') }}" aria-label="Timeline Curator home">
            <span>Timeline</span> Curator
        </a>

        <nav class="landing-nav" aria-label="Homepage">
            <a href="#who-it-serves">Who it serves</a>
            <a href="#how-it-works">How it works</a>
            <a href="{{ route('guide') }}">Setup guide</a>
        </nav>

        <div class="landing-account">
            @auth
                <a class="button compact" href="{{ route('timeline') }}">Open Timeline</a>
            @else
                <a class="landing-sign-in" href="{{ route('login') }}">Sign in</a>
                <a class="button compact" href="{{ route('register') }}">Create your Timeline</a>
            @endauth
        </div>
    </header>

    <main>
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-hero-copy">
                <p class="landing-kicker">A private research feed, directed by you</p>
                <h1 id="landing-title">Timeline builds a private research feed from the topics you choose.</h1>
                <p class="landing-lede">Choose the subjects, sources, depth, and pace. Your Codex task researches them, checks the evidence, and brings the strongest stories into a Timeline you can shape with feedback.</p>

                <div class="landing-actions">
                    @auth
                        <a class="button landing-primary" href="{{ route('timeline') }}">Open your Timeline</a>
                    @else
                        <a class="button landing-primary" href="{{ route('register') }}">Create your Timeline</a>
                    @endauth
                    <a class="landing-guide-link" href="{{ route('guide') }}">
                        <span>Read the setup guide</span>
                        <span aria-hidden="true">↗</span>
                    </a>
                </div>

                <dl class="landing-facts">
                    <div>
                        <dt>Your topics</dt>
                        <dd>Broad interests or precise beats</dd>
                    </div>
                    <div>
                        <dt>Your policy</dt>
                        <dd>Sources, depth, exclusions, and pace</dd>
                    </div>
                    <div>
                        <dt>Your feedback</dt>
                        <dd>Every run becomes better aligned</dd>
                    </div>
                </dl>
            </div>

            <figure class="landing-artwork">
                <div class="landing-artwork-frame">
                    <img
                        src="{{ asset('images/timeline-home-hero-1400.jpg') }}"
                        srcset="{{ asset('images/timeline-home-hero-720.jpg') }} 720w, {{ asset('images/timeline-home-hero-1400.jpg') }} 1400w"
                        sizes="(max-width: 860px) calc(100vw - 32px), 46vw"
                        width="1400"
                        height="735"
                        alt="An editorial collage representing stories gathered into a connected Timeline."
                        fetchpriority="high"
                        decoding="async"
                    >
                </div>
                <figcaption>
                    <span class="landing-caption-mark" aria-hidden="true"></span>
                    Stories arrive with concise context, inspected sources, and relevant visual media when available.
                </figcaption>
            </figure>
        </section>

        <section class="landing-audiences" id="who-it-serves" aria-labelledby="audiences-title">
            <header class="landing-section-heading">
                <p>Who Timeline serves</p>
                <h2 id="audiences-title">Used for work, research, communities, and personal interests.</h2>
                <p>Timeline is not tied to a news category. A policy can follow an industry, a neighbourhood, a body of research, a team, or a small obsession.</p>
            </header>

            <div class="audience-grid">
                <article class="audience-card audience-card-founder">
                    <span class="audience-number">01</span>
                    <div>
                        <p class="audience-role">Founders &amp; operators</p>
                        <h3>Monitor markets while running the business.</h3>
                        <p>Track competitors, customer behaviour, regulation, and new tools in one sourced briefing instead of a folder of unread alerts.</p>
                    </div>
                    <ul>
                        <li>Competitor launches</li>
                        <li>Policy changes</li>
                        <li>Industry signals</li>
                    </ul>
                </article>

                <article class="audience-card audience-card-researcher">
                    <span class="audience-number">02</span>
                    <div>
                        <p class="audience-role">Researchers &amp; specialists</p>
                        <h3>Keep up with a field between deep-work sessions.</h3>
                        <p>Ask for primary papers, credible commentary, contested findings, and the level of technical detail your work requires.</p>
                    </div>
                    <ul>
                        <li>New publications</li>
                        <li>Independent scrutiny</li>
                        <li>Open questions</li>
                    </ul>
                </article>

                <article class="audience-card audience-card-community">
                    <span class="audience-number">03</span>
                    <div>
                        <p class="audience-role">Communities &amp; public-interest teams</p>
                        <h3>Bring scattered local information into one place.</h3>
                        <p>Bring together council notices, transport changes, public meetings, local reporting, and the events people need to know about.</p>
                    </div>
                    <ul>
                        <li>Local decisions</li>
                        <li>Events and notices</li>
                        <li>Community reporting</li>
                    </ul>
                </article>

                <article class="audience-card audience-card-curious">
                    <span class="audience-number">04</span>
                    <div>
                        <p class="audience-role">Fans, makers &amp; curious people</p>
                        <h3>Follow personal interests without engagement bait.</h3>
                        <p>Follow a club, an artist, a craft, a game, a garden, or a place without surrendering the feed to engagement bait.</p>
                    </div>
                    <ul>
                        <li>Fixtures and releases</li>
                        <li>Projects and techniques</li>
                        <li>Niche discoveries</li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="landing-workflow" id="how-it-works" aria-labelledby="workflow-title">
            <div class="workflow-intro">
                <p>How it works</p>
                <h2 id="workflow-title">Your policy controls every curation run.</h2>
                <p>Timeline stores your choices and presents the result. Research happens through the Timeline plugin in your own authenticated Codex environment.</p>
            </div>

            <ol class="workflow-steps">
                <li>
                    <span>1</span>
                    <div>
                        <h3>Describe the coverage</h3>
                        <p>Start with a catalogue topic or write a precise brief. Add directives for freshness, source quality, range, depth, or exclusions.</p>
                    </div>
                </li>
                <li>
                    <span>2</span>
                    <div>
                        <h3>Let Codex investigate</h3>
                        <p>A scheduled or on-demand run searches broadly, inspects sources, verifies claims and media, then selects distinct stories.</p>
                    </div>
                </li>
                <li>
                    <span>3</span>
                    <div>
                        <h3>Read and respond</h3>
                        <p>Open a calm, visual feed. Story-specific feedback tells the next run what was useful, repetitive, shallow, stale, or worth more attention.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="landing-close" aria-labelledby="landing-close-title">
            <div>
                <p>Ready when you are</p>
                <h2 id="landing-close-title">Create an account and add your first topic.</h2>
            </div>
            <div class="landing-close-actions">
                @auth
                    <a class="button landing-primary" href="{{ route('timeline') }}">Open your Timeline</a>
                @else
                    <a class="button landing-primary" href="{{ route('register') }}">Create your Timeline</a>
                @endauth
                <a href="{{ route('guide') }}">See the complete setup</a>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <a class="landing-brand" href="{{ route('home') }}"><span>Timeline</span> Curator</a>
        <p>A private curation workspace for people who want to choose what reaches them.</p>
        <nav aria-label="Footer">
            <a href="{{ route('guide') }}">Setup guide</a>
            @guest<a href="{{ route('login') }}">Sign in</a>@endguest
        </nav>
    </footer>
</body>
</html>
