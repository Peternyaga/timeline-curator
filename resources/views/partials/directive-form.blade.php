<form method="post" action="{{ $action }}" class="stack">
    @csrf @method('PATCH')
    <label>Instruction<textarea name="body" required maxlength="3000">{{ $directive->body }}</textarea></label>
    <div class="split">
        <label>Strength<select name="strength"><option value="soft" @selected($directive->strength === 'soft')>Soft preference</option><option value="hard" @selected($directive->strength === 'hard')>Hard rule</option></select></label>
        <label>Expires<input name="expires_at" type="date" value="{{ $directive->expires_at?->format('Y-m-d') }}"></label>
    </div>
    <label>Blocked domains<input name="blocked_domains" value="{{ implode(', ', data_get($directive->structured_rules, 'blocked_domains', [])) }}"></label>
    <button class="button compact" type="submit">Save changes</button>
</form>
