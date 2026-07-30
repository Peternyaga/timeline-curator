<main class="guide-shell">
    <header class="guide-hero">
        @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
        <div class="guide-hero-copy">
            <p class="eyebrow">YOUR FRIENDLY STARTING POINT</p>
            <h1>Turn your interests into a living Timeline.</h1>
            <p class="guide-lede">You are five small steps away from a private research feed that keeps itself fresh. No API key is required.</p>
            <div class="guide-hero-actions">
                @auth
                    <a class="button" href="{{ route('policy') }}">Start with your interests</a>
                    <a class="text-link" href="{{ route('timeline') }}">Open my feed</a>
                @else
                    <a class="button" href="{{ route('register') }}">Create your Timeline</a>
                    <a class="text-link" href="#install">I already have an account</a>
                @endauth
            </div>
        </div>
        <aside class="guide-promise" aria-label="What you will accomplish">
            <span aria-hidden="true">✦</span>
            <strong>About 10 minutes</strong>
            <p>Choose a topic, install the plugin, connect Codex, publish your first stories, and schedule the next run.</p>
        </aside>
    </header>

    <nav class="guide-progress" aria-label="Setup steps">
        <a href="#interests"><span>1</span> Interests</a>
        <a href="#install"><span>2</span> Install</a>
        <a href="#connect"><span>3</span> Connect</a>
        <a href="#first-run"><span>4</span> First run</a>
        <a href="#schedule"><span>5</span> Schedule</a>
    </nav>

    <div class="guide-steps">
        <section class="guide-step" id="interests">
            <div class="guide-step-number" aria-hidden="true">01</div>
            <div class="guide-step-body">
                <p class="eyebrow">TEACH TIMELINE WHAT MATTERS</p>
                <h2>Choose your interests</h2>
                <p>Open <strong>Policy</strong>, choose a suggested topic, and make the coverage brief your own. Add directives for preferences such as primary sources, useful depth, accessibility, or diverse viewpoints.</p>
                <ul class="guide-checklist">
                    <li>Add at least one active topic.</li>
                    <li>Choose a daily run limit from 1–10.</li>
                    <li>Save any source or coverage preferences.</li>
                </ul>
                @auth<a class="button compact secondary" href="{{ route('policy') }}">Configure my policy</a>@endauth
            </div>
        </section>

        <section class="guide-step" id="install">
            <div class="guide-step-number" aria-hidden="true">02</div>
            <div class="guide-step-body">
                <p class="eyebrow">ADD TIMELINE TO CODEX</p>
                <h2>Install Timeline Curator</h2>
                <div class="guide-option-grid">
                    <article>
                        <span class="guide-option-label">CODEX DESKTOP</span>
                        <ol>
                            <li>Open <strong>Settings → Plugins → Marketplaces</strong>.</li>
                            <li>Select <strong>Add marketplace</strong>.</li>
                            <li>Enter <code>Peternyaga/timeline-curator</code>.</li>
                            <li>Open <strong>Vumbua Labs</strong> and install <strong>Timeline Curator</strong>.</li>
                            <li>Start a new Codex task.</li>
                        </ol>
                    </article>
                    <article>
                        <span class="guide-option-label">CODEX CLI</span>
                        <p>Run these commands in your terminal:</p>
                        <pre><code>codex plugin marketplace add Peternyaga/timeline-curator
codex plugin add timeline-curator@vumbua-labs</code></pre>
                        <p class="guide-note">Plugins load when a task starts, so open a new task after installation.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="guide-step" id="connect">
            <div class="guide-step-number" aria-hidden="true">03</div>
            <div class="guide-step-body">
                <p class="eyebrow">ONE PRIVATE CONNECTION</p>
                <h2>Connect Codex to your account</h2>
                <p>Installation may open Timeline automatically. If it does not, run:</p>
                <pre><code>codex mcp login timeline</code></pre>
                <ol>
                    <li>Sign in to Timeline in the browser window.</li>
                    <li>Review the requested access and select <strong>Approve</strong>.</li>
                    <li>Keep Codex open while the browser returns to its local callback.</li>
                </ol>
                <p class="guide-safety"><strong>Keep it private:</strong> never share access tokens, authorization codes, cookies, or the complete localhost callback URL.</p>
            </div>
        </section>

        <section class="guide-step" id="first-run">
            <div class="guide-step-number" aria-hidden="true">04</div>
            <div class="guide-step-body">
                <p class="eyebrow">BRING THE FEED TO LIFE</p>
                <h2>Publish your first curation</h2>
                <p>In a new Codex task, send this exact instruction:</p>
                <blockquote>@Timeline Curator Run my Timeline curation cycle now. Monitor it through completion and publish the results to Timeline.</blockquote>
                <p>Codex will retrieve your policy, research broadly, verify evidence and media, and publish suitable stories. A valid run may return no stories when the evidence is weak.</p>
                @auth<a class="button compact secondary" href="{{ route('timeline') }}">Check my Timeline</a>@endauth
            </div>
        </section>

        <section class="guide-step" id="schedule">
            <div class="guide-step-number" aria-hidden="true">05</div>
            <div class="guide-step-body">
                <p class="eyebrow">KEEP THE SIGNAL MOVING</p>
                <h2>Schedule the same instruction</h2>
                <p>Create a recurring Codex task and use the exact first-run instruction as its prompt. Choose a cadence that fits your daily run limit—once every morning is a great place to start.</p>
                <div class="guide-success">
                    <span aria-hidden="true">✓</span>
                    <div><strong>You are ready.</strong><p>When the scheduled task reports completion and new stories appear in your feed, Timeline is working end to end.</p></div>
                </div>
            </div>
        </section>
    </div>

    <section class="guide-help" aria-labelledby="guide-help-title">
        <div>
            <p class="eyebrow">QUICK RECOVERY</p>
            <h2 id="guide-help-title">If something does not work</h2>
        </div>
        <div class="guide-help-grid">
            <article><strong>Plugin not appearing?</strong><p>Restart Codex or open a new task after installation.</p></article>
            <article><strong>Authentication required?</strong><p>Run <code>codex mcp login timeline</code> once, then retry the task.</p></article>
            <article><strong>No stories yet?</strong><p>Confirm that you have an active topic, then review the completed run. Evidence-first empty runs are normal.</p></article>
            <article><strong>Update available?</strong><pre><code>codex plugin marketplace upgrade vumbua-labs
codex plugin add timeline-curator@vumbua-labs</code></pre></article>
        </div>
    </section>
</main>
