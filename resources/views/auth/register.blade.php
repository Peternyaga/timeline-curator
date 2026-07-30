<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account — Timeline Curator</title>@vite('resources/css/app.css')</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand" href="{{ route('home') }}"><span>TIMELINE</span> CURATOR</a>
    <p class="eyebrow">NEW ACCOUNT</p><h1>Create your Timeline</h1>
    <form class="stack" method="post" action="{{ route('register') }}">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required autocomplete="name"></label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
        <label>Password<input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
        <label>Confirm password<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label>
        <input name="timezone" type="hidden" id="timezone" value="{{ old('timezone', 'UTC') }}">
        @if ($errors->any())<div class="form-errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <button class="button" type="submit">Create account</button>
    </form>
    <p class="auth-guide-link">New to Codex plugins? <a href="{{ route('guide') }}">See the friendly setup guide</a>.</p>
    <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
</main>
<script>document.getElementById('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';</script>
</body></html>
