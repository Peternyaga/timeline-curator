<section class="run-now-panel">
    <div><p class="eyebrow">CODEX HANDOFF</p><h2>Want to process approvals now?</h2><p>Queued applications are picked up by the next combined task automatically.</p></div>
    <button class="button secondary" type="button" data-copy-job-prompt data-prompt="Use the Timeline Curator skill to process up to five approved job applications now. Do not run a new curation cycle. Pause for missing, sensitive, legal, login, or CAPTCHA input and record evidence for every outcome.">Copy run-now prompt</button>
    <span data-copy-job-status aria-live="polite"></span>
</section>

@if($applications->isEmpty())
    <section class="jobs-empty"><h2>No applications yet</h2><p>Approve a curated match and its application trail will appear here.</p></section>
@else
    <div class="application-list">
        @foreach($applications as $application)
            <article class="application-card">
                <header>
                    <div><p class="eyebrow">{{ $application->jobCuration->employer }}</p><h2>{{ $application->jobCuration->title }}</h2></div>
                    <span class="status-pill {{ $application->status === 'submitted' ? 'success' : '' }}">{{ str_replace('_', ' ', $application->status) }}</span>
                </header>
                <p>Approved {{ $application->approved_at->format('M j, Y \a\t H:i') }} · {{ strtoupper($application->channel) }}</p>
                @if($application->status === 'leased')<p>Codex is working on this application until {{ $application->lease_expires_at?->format('H:i') }}.</p>@endif
                @if($application->manual_reason)<div class="flash error">{{ $application->manual_reason }}</div>@endif

                @php($questionnaire = $application->questionnaires->firstWhere('status', 'pending'))
                @if($questionnaire)
                    <form method="post" enctype="multipart/form-data" action="{{ route('jobs.questionnaires.update', $questionnaire) }}" class="questionnaire-form">
                        @csrf @method('PUT')
                        <h3>Information needed</h3>
                        @if(data_get($questionnaire->schema_payload, 'message'))<p>{{ data_get($questionnaire->schema_payload, 'message') }}</p>@endif
                        @foreach(data_get($questionnaire->schema_payload, 'fields', []) as $field)
                            <div class="generated-field {{ $field['classification'] !== 'normal' ? 'is-sensitive' : '' }}">
                                <label for="field-{{ $questionnaire->id }}-{{ $field['id'] }}">{{ $field['label'] }} @if($field['required'])<span aria-label="required">*</span>@endif</label>
                                @if($field['help_text'])<small>{{ $field['help_text'] }}</small>@endif
                                @if($field['classification'] !== 'normal')<p class="sensitive-note">Answer explicitly. This answer stays with this application and will not be reused.</p>@endif
                                @switch($field['type'])
                                    @case('long_text')<textarea id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" name="answers[{{ $field['id'] }}]" @required($field['required'])></textarea>@break
                                    @case('single_select')<select id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" name="answers[{{ $field['id'] }}]" @required($field['required'])><option value="">Choose…</option>@foreach($field['choices'] as $choice)<option value="{{ $choice }}">{{ $choice }}</option>@endforeach</select>@break
                                    @case('multi_select')<select id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" name="answers[{{ $field['id'] }}][]" multiple @required($field['required'])>@foreach($field['choices'] as $choice)<option value="{{ $choice }}">{{ $choice }}</option>@endforeach</select>@break
                                    @case('boolean')<input id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" type="checkbox" name="answers[{{ $field['id'] }}]" value="1" @required($field['required'])>@break
                                    @case('file')<input id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" type="file" name="answers[{{ $field['id'] }}]" accept=".pdf,.docx" @required($field['required'])>@break
                                    @default<input id="field-{{ $questionnaire->id }}-{{ $field['id'] }}" type="{{ in_array($field['type'], ['email', 'number', 'date']) ? $field['type'] : 'text' }}" name="answers[{{ $field['id'] }}]" @required($field['required'])>
                                @endswitch
                                @if($field['classification'] === 'normal' && $field['type'] !== 'file')<label class="save-answer"><input type="checkbox" name="save[{{ $field['id'] }}]" value="1"> Save this answer to my profile</label>@endif
                            </div>
                        @endforeach
                        <button class="button" type="submit">Save and let Codex resume</button>
                    </form>
                @endif

                @if($application->materials->isNotEmpty())<div class="job-source-row"><strong>Retained materials:</strong>@foreach($application->materials as $material)<a href="{{ route('jobs.applications.materials.download', [$application, $material]) }}">{{ $material->filename ?: str_replace('_', ' ', $material->kind) }}</a>@endforeach</div>@endif
                <div class="application-actions">
                    @if(in_array($application->status, ['queued', 'ready_to_resume']))<form method="post" action="{{ route('jobs.applications.cancel', $application) }}">@csrf<button class="button secondary compact" type="submit">Cancel approval</button></form>@endif
                    @if($application->status !== 'leased')<form method="post" action="{{ route('jobs.applications.destroy', $application) }}" data-delete-application>@csrf @method('DELETE')<button class="link-button danger" type="submit">Delete application data</button></form>@endif
                </div>
            </article>
        @endforeach
    </div>
    {{ $applications->links() }}
@endif
