<?php

namespace App\Http\Controllers;

use App\Curation\CurationException;
use App\Jobs\JobApplicationService;
use App\Models\ApplicationMaterial;
use App\Models\JobApplication;
use App\Models\JobCuration;
use App\Models\JobProfile;
use Symfony\Component\HttpFoundation\HeaderUtils;

class JobApplicationController extends Controller
{
    public function approve(string $job, JobApplicationService $applications)
    {
        try {
            $applications->approve(
                JobCuration::query()->findOrFail($job),
                JobProfile::query()->with('documents')->firstOrFail(),
            );
        } catch (CurationException $exception) {
            return redirect()->to(route('jobs.matches').'#job-'.$job)->withErrors(['application' => $exception->getMessage()]);
        }

        return redirect()->route('jobs.applications')->with('status', 'Application approved and queued for Codex.');
    }

    public function cancel(string $application)
    {
        $application = JobApplication::query()->findOrFail($application);
        if (! in_array($application->status, ['queued', 'ready_to_resume'], true)) {
            return redirect()->route('jobs.applications')->withErrors(['application' => 'Only a waiting application can be cancelled.']);
        }
        $application->update(['status' => 'cancelled', 'completed_at' => now()]);
        $application->events()->create(['event' => 'cancelled', 'metadata' => null]);

        return redirect()->route('jobs.applications')->with('status', 'Application cancelled.');
    }

    public function destroy(string $application)
    {
        $application = JobApplication::query()->findOrFail($application);
        if ($application->status === 'leased') {
            return redirect()->route('jobs.applications')->withErrors(['application' => 'Application data cannot be deleted while Codex is working on it.']);
        }
        $application->delete();

        return redirect()->route('jobs.applications')->with('status', 'Application data permanently deleted.');
    }

    public function material(string $application, string $material)
    {
        $application = JobApplication::query()->findOrFail($application);
        $material = ApplicationMaterial::query()->where('job_application_id', $application->id)->findOrFail($material);
        $contents = base64_decode($material->content, true);
        abort_if($contents === false, 404);

        return response($contents, 200, [
            'Content-Type' => $material->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $material->filename ?: $material->kind.'.txt',
                'application-material',
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
