<?php

namespace App\Http\Controllers;

use App\Models\JobCuration;
use App\Models\JobFeedbackEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobFeedbackController extends Controller
{
    public function store(Request $request, string $job): JsonResponse|RedirectResponse
    {
        $job = JobCuration::query()->findOrFail($job);
        $allowedTags = collect($job->feedback_tags ?? [])->pluck('id')->filter()->values()->all();
        $data = $request->validate([
            'interest_score' => ['required', 'integer', 'between:1,5'],
            'match_score' => ['required', 'integer', 'between:1,5'],
            'semantic_tags' => ['array', 'max:5'],
            'semantic_tags.*' => ['string', 'distinct', Rule::in($allowedTags)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        JobFeedbackEvent::query()->updateOrCreate(['job_curation_id' => $job->id], $data);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Job feedback saved.', 'job_id' => $job->id]);
        }

        return redirect()->to(route('jobs.matches').'#job-'.$job->id)->with('status', 'Job feedback saved.');
    }
}
