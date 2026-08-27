<?php

namespace App\Http\Controllers;

use App\Jobs\JobProfileDocumentService;
use App\Models\JobApplication;
use App\Models\JobProfile;
use App\Models\JobProfileDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

class JobProfileDocumentController extends Controller
{
    public function store(Request $request, JobProfileDocumentService $documents)
    {
        $data = $request->validate([
            'document' => ['required', 'file', 'max:5120', 'mimes:pdf,docx'],
            'kind' => ['required', 'in:resume,supporting'],
            'label' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $profile = JobProfile::query()->firstOrCreate([], [
            'search_preferences' => [], 'personal_details' => [], 'career_details' => [], 'reusable_answers' => [],
        ]);
        $documents->store($profile, $data['document'], $data['kind'], $data['label'], $request->boolean('is_default'));
        $profile->increment('profile_version');

        return redirect()->route('jobs.profile')->with('status', 'Private document uploaded.');
    }

    public function download(string $document, JobProfileDocumentService $documents)
    {
        $document = JobProfileDocument::query()->findOrFail($document);

        return response($documents->contents($document), 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $document->original_name,
                'profile-document',
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(string $document, JobProfileDocumentService $documents)
    {
        $document = JobProfileDocument::query()->findOrFail($document);
        $inUse = JobApplication::query()
            ->whereIn('status', ['queued', 'leased', 'needs_information', 'ready_to_resume', 'needs_manual_action'])
            ->get(['profile_snapshot'])
            ->contains(fn (JobApplication $application) => collect($application->profile_snapshot['documents'] ?? [])->pluck('id')->contains($document->id));
        if ($inUse) {
            return redirect()->route('jobs.profile')->withErrors(['document' => 'Cancel or finish the approved application using this document before deleting it.']);
        }
        $profile = $document->profile;
        $documents->delete($document);
        $profile->increment('profile_version');

        return redirect()->route('jobs.profile')->with('status', 'Document deleted.');
    }
}
