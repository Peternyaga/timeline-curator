<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in — Timeline Curator</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand" href="{{ route('home') }}">Timeline<span>Curator</span></a>
    <p class="eyebrow">WELCOME BACK</p><h1>Sign in to your timeline</h1>
    <form class="stack" method="post" action="{{ route('login') }}">
        @csrf
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
        <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
        <label class="check"><input name="remember" type="checkbox" value="1"> Keep me signed in</label>
        @if ($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif
        <button class="button" type="submit">Sign in</button>
    </form>
    <p class="auth-switch">New here? <a href="{{ route('register') }}">Create an account</a></p>
</main>
</body></html>
