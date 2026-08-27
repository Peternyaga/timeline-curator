@php($preferences = $profile?->search_preferences ?? [])
@php($personal = $profile?->personal_details ?? [])
@php($career = $profile?->career_details ?? [])
<section class="profile-readiness">
    <div><strong>Search profile</strong><span>{{ $profile?->searchReady() ? 'Ready' : 'Needs target roles and locations' }}</span></div>
    <div><strong>Application profile</strong><span>{{ $profile?->applicationReady() ? 'Ready' : 'Codex may ask for missing details' }}</span></div>
    <div><strong>Version</strong><span>{{ $profile?->profile_version ?? 1 }}</span></div>
</section>

<form method="post" action="{{ route('jobs.profile.update') }}" class="job-profile-form">
    @csrf @method('PUT')
    <section><p class="eyebrow">SEARCH GOALS</p><h2>What should Codex look for?</h2>
        <label>Target roles <small>One per line</small><textarea name="target_roles">{{ implode("\n", $preferences['target_roles'] ?? []) }}</textarea></label>
        <label>Industries <small>One per line</small><textarea name="industries">{{ implode("\n", $preferences['industries'] ?? []) }}</textarea></label>
        <label>Locations <small>One per line; include “Worldwide” if appropriate</small><textarea name="locations">{{ implode("\n", $preferences['locations'] ?? []) }}</textarea></label>
        <fieldset><legend>Work arrangement</legend>@foreach(['remote' => 'Remote', 'hybrid' => 'Hybrid', 'on-site' => 'On-site'] as $value => $label)<label><input type="checkbox" name="work_modes[]" value="{{ $value }}" @checked(in_array($value, $preferences['work_modes'] ?? []))> {{ $label }}</label>@endforeach</fieldset>
        <label>Employment types <small>One per line</small><textarea name="employment_types">{{ implode("\n", $preferences['employment_types'] ?? []) }}</textarea></label>
        <div class="form-grid"><label>Salary currency<input name="salary_currency" maxlength="3" value="{{ data_get($preferences, 'salary.currency') }}"></label><label>Minimum<input type="number" min="0" name="salary_min" value="{{ data_get($preferences, 'salary.min') }}"></label><label>Maximum<input type="number" min="0" name="salary_max" value="{{ data_get($preferences, 'salary.max') }}"></label></div>
        <label>Employers to exclude <small>One per line</small><textarea name="excluded_employers">{{ implode("\n", $preferences['excluded_employers'] ?? []) }}</textarea></label>
    </section>
    <section><p class="eyebrow">CONTACT & ELIGIBILITY</p><h2>Your application details</h2>
        <div class="form-grid"><label>Full legal name<input name="full_name" value="{{ $personal['full_name'] ?? '' }}"></label><label>Preferred name<input name="preferred_name" value="{{ $personal['preferred_name'] ?? '' }}"></label><label>Email<input type="email" name="email" value="{{ $personal['email'] ?? '' }}"></label><label>Phone<input name="phone" value="{{ $personal['phone'] ?? '' }}"></label></div>
        <label>Address<textarea name="address">{{ $personal['address'] ?? '' }}</textarea></label><label>Work authorization, sponsorship, relocation, and travel notes<textarea name="eligibility">{{ $personal['eligibility'] ?? '' }}</textarea></label><label>Availability or notice period<textarea name="availability">{{ $personal['availability'] ?? '' }}</textarea></label>
    </section>
    <section><p class="eyebrow">CAREER FACTS</p><h2>Facts Codex may use truthfully</h2>
        <label>Professional summary<textarea name="professional_summary">{{ $career['professional_summary'] ?? '' }}</textarea></label><label>Experience<textarea name="experience" rows="10">{{ $career['experience'] ?? '' }}</textarea></label><label>Education<textarea name="education">{{ $career['education'] ?? '' }}</textarea></label><label>Skills<textarea name="skills">{{ $career['skills'] ?? '' }}</textarea></label><label>Certifications<textarea name="certifications">{{ $career['certifications'] ?? '' }}</textarea></label><label>Portfolio and professional links<textarea name="links">{{ $career['links'] ?? '' }}</textarea></label><label>Languages<textarea name="languages">{{ $career['languages'] ?? '' }}</textarea></label><label>Reusable application answers<textarea name="reusable_answers" rows="8">{{ data_get($profile?->reusable_answers, 'notes') }}</textarea></label>
    </section>
    <label class="enable-job-search"><input type="checkbox" name="enabled" value="1" @checked($profile?->enabled)> Include job searches in my combined Timeline task</label>
    <button class="button" type="submit">Save job profile</button>
</form>

<section class="document-panel"><p class="eyebrow">PRIVATE DOCUMENTS</p><h2>Résumés and supporting files</h2><p>PDF or DOCX, up to 5 MB. Files are encrypted and available to Codex only after you approve one job.</p>
    @if($profile?->documents->isNotEmpty())<div class="document-list">@foreach($profile->documents as $document)<div><span><strong>{{ $document->label }}</strong> · {{ $document->kind }} @if($document->is_default)· default @endif</span><span><a href="{{ route('jobs.documents.download', $document) }}">Download</a><form method="post" action="{{ route('jobs.documents.destroy', $document) }}">@csrf @method('DELETE')<button class="link-button danger" type="submit">Delete</button></form></span></div>@endforeach</div>@endif
    <form method="post" enctype="multipart/form-data" action="{{ route('jobs.documents.store') }}" class="document-upload">@csrf<div class="form-grid"><label>Label<input name="label" required></label><label>Type<select name="kind"><option value="resume">Résumé</option><option value="supporting">Supporting document</option></select></label><label>File<input type="file" name="document" accept=".pdf,.docx" required></label></div><label><input type="checkbox" name="is_default" value="1"> Make this my default résumé</label><button class="button secondary" type="submit">Upload privately</button></form>
</section>
