<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobCuration;
use App\Models\JobProfile;

class JobsController extends Controller
{
    public function matches()
    {
        return view('jobs.index', [
            'tab' => 'matches',
            'profile' => JobProfile::query()->with('documents')->first(),
            'jobs' => JobCuration::query()->forJobsWorkspace()->latest('retrieved_at')->paginate(20),
            'applications' => null,
        ]);
    }

    public function applications()
    {
        return view('jobs.index', [
            'tab' => 'applications',
            'profile' => JobProfile::query()->with('documents')->first(),
            'jobs' => null,
            'applications' => JobApplication::query()
                ->with(['jobCuration', 'questionnaires' => fn ($query) => $query->latest(), 'materials'])
                ->latest('approved_at')->paginate(20),
        ]);
    }

    public function profile()
    {
        return view('jobs.index', [
            'tab' => 'profile',
            'profile' => JobProfile::query()->with('documents')->first(),
            'jobs' => null,
            'applications' => null,
        ]);
    }
}
