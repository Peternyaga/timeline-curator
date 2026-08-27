@extends('layouts.app')

@section('title', 'Jobs · Timeline Curator')

@section('content')
<main class="jobs-shell">
    <header class="jobs-header">
        <div>
            <p class="eyebrow">JOB SEARCH COMPANION</p>
            <h1>Jobs worth your time</h1>
            <p>Curated matches, explicit approval, and a clear application trail.</p>
        </div>
        @if($profile?->enabled)<span class="status-pill success">Job search on</span>@else<span class="status-pill">Job search off</span>@endif
    </header>

    @if(session('status'))<div class="flash success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif

    <nav class="jobs-tabs" aria-label="Jobs workspace">
        <a href="{{ route('jobs.matches') }}" @if($tab === 'matches') aria-current="page" @endif>Matches</a>
        <a href="{{ route('jobs.applications') }}" @if($tab === 'applications') aria-current="page" @endif>Applications</a>
        <a href="{{ route('jobs.profile') }}" @if($tab === 'profile') aria-current="page" @endif>Profile</a>
    </nav>

    @include('jobs.'.$tab)
</main>
@endsection
