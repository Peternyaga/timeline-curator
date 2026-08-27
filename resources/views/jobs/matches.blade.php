@if(!$profile || !$profile->searchReady())
    <section class="jobs-empty">
        <p class="eyebrow">FIRST STEP</p>
        <h2>Tell Curator what you are looking for</h2>
        <p>Add target roles and locations before Codex starts bringing job matches here.</p>
        <a class="button" href="{{ route('jobs.profile') }}">Create job profile</a>
    </section>
@elseif($jobs->isEmpty())
    <section class="jobs-empty">
        <h2>No matches yet</h2>
        <p>Your combined Timeline task will search for jobs when job search is enabled.</p>
        @if(!$profile->enabled)<a class="button" href="{{ route('jobs.profile') }}">Enable job search</a>@endif
    </section>
@else
    <div class="job-card-list">
        @foreach($jobs as $job)
            <article class="job-card" id="job-{{ $job->id }}">
                <header>
                    <div>
                        <p class="eyebrow">{{ $job->employer }}</p>
                        <h2>{{ $job->title }}</h2>
                        <p class="job-meta">{{ $job->location ?: 'Location not stated' }} · {{ $job->workplace_type ?: 'Work arrangement not stated' }} · {{ $job->employment_type ?: 'Employment type not stated' }}</p>
                    </div>
                    <span class="status-pill">{{ strtoupper($job->application_channel) }}</span>
                </header>

                <div class="job-dates">
                    <span>Posted: <strong>{{ $job->posted_at?->format('M j, Y') ?? 'Unknown' }}</strong></span>
                    <span>Found: <strong>{{ $job->retrieved_at->format('M j, Y') }}</strong></span>
                    <span>Deadline: <strong>{{ $job->deadline_at?->format('M j, Y') ?? 'Not stated' }}</strong></span>
                </div>

                @php($salary = $job->salary)
                @if($salary && ($salary['min'] !== null || $salary['max'] !== null))
                    <p class="salary-line">Salary: {{ $salary['currency'] ? $salary['currency'].' ' : '' }}{{ $salary['min'] !== null ? number_format($salary['min']) : '?' }}–{{ $salary['max'] !== null ? number_format($salary['max']) : '?' }}{{ $salary['period'] ? ' / '.$salary['period'] : '' }}</p>
                @else
                    <p class="salary-line">Salary: Not stated</p>
                @endif

                <div class="job-columns">
                    <section><h3>What the role involves</h3><ul>@foreach($job->summary_points as $point)<li>{{ $point }}</li>@endforeach</ul></section>
                    <section><h3>Why it may fit</h3><ul>@foreach($job->match_points as $point)<li>{{ $point }}</li>@endforeach</ul></section>
                    @if($job->gaps)<section><h3>Check before applying</h3><ul>@foreach($job->gaps as $gap)<li>{{ $gap }}</li>@endforeach</ul></section>@endif
                </div>

                <div class="job-source-row">
                    @foreach($job->sources as $source)
                        <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->role === 'primary' ? 'Original listing' : $source->title }}</a>
                    @endforeach
                </div>

                <details class="feedback-disclosure" @if($job->feedback) open @endif>
                    <summary><span>{{ $job->feedback ? 'Update your rating' : 'Rate this match' }}</span>@if($job->feedback)<span class="feedback-saved">Saved</span>@endif</summary>
                    <form method="post" action="{{ route('jobs.feedback.store', $job) }}" class="feedback" data-feedback-form>
                        @csrf
                        <label>Interest <input type="range" name="interest_score" min="1" max="5" value="{{ $job->feedback?->interest_score ?? 3 }}"></label>
                        <label>Match accuracy <input type="range" name="match_score" min="1" max="5" value="{{ $job->feedback?->match_score ?? 3 }}"></label>
                        <div class="feedback-tags">
                            @foreach($job->feedback_tags as $tag)
                                <label><input type="checkbox" name="semantic_tags[]" value="{{ $tag['id'] }}" @checked(in_array($tag['id'], $job->feedback?->semantic_tags ?? []))> {{ $tag['label'] }}</label>
                            @endforeach
                        </div>
                        <textarea name="comment" placeholder="Optional note for future searches">{{ $job->feedback?->comment }}</textarea>
                        <div class="feedback-actions"><button class="button compact" type="submit">Save rating</button><span data-form-status aria-live="polite"></span></div>
                    </form>
                </details>

                <footer class="job-actions">
                    @if($job->latestApplication)
                        <span class="status-pill">{{ str_replace('_', ' ', $job->latestApplication->status) }}</span>
                        <a href="{{ route('jobs.applications') }}">View application</a>
                    @else
                        <form method="post" action="{{ route('jobs.applications.approve', $job) }}" data-approve-application>
                            @csrf
                            <button class="button" type="submit">Approve this application</button>
                        </form>
                        <p>Approves one attempt. Codex may tailor truthful materials and submit to {{ $job->application_channel === 'email' ? $job->application_email : parse_url($job->application_url, PHP_URL_HOST) }}.</p>
                    @endif
                </footer>
            </article>
        @endforeach
    </div>
    {{ $jobs->links() }}
@endif
