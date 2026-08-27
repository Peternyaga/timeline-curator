<?php

namespace App\Http\Controllers;

use App\Models\JobProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JobProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'target_roles' => ['nullable', 'string', 'max:3000'],
            'industries' => ['nullable', 'string', 'max:3000'],
            'locations' => ['nullable', 'string', 'max:3000'],
            'work_modes' => ['array'],
            'work_modes.*' => ['string', 'in:remote,hybrid,on-site'],
            'employment_types' => ['nullable', 'string', 'max:1000'],
            'salary_currency' => ['nullable', 'string', 'max:3'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'gte:salary_min'],
            'excluded_employers' => ['nullable', 'string', 'max:3000'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:320'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'eligibility' => ['nullable', 'string', 'max:3000'],
            'availability' => ['nullable', 'string', 'max:1000'],
            'professional_summary' => ['nullable', 'string', 'max:5000'],
            'experience' => ['nullable', 'string', 'max:20000'],
            'education' => ['nullable', 'string', 'max:10000'],
            'skills' => ['nullable', 'string', 'max:10000'],
            'certifications' => ['nullable', 'string', 'max:5000'],
            'links' => ['nullable', 'string', 'max:5000'],
            'languages' => ['nullable', 'string', 'max:3000'],
            'reusable_answers' => ['nullable', 'string', 'max:20000'],
        ]);

        $searchPreferences = [
            'target_roles' => $this->lines($data['target_roles'] ?? ''),
            'industries' => $this->lines($data['industries'] ?? ''),
            'locations' => $this->lines($data['locations'] ?? ''),
            'work_modes' => array_values($data['work_modes'] ?? []),
            'employment_types' => $this->lines($data['employment_types'] ?? ''),
            'salary' => ['currency' => strtoupper($data['salary_currency'] ?? ''), 'min' => $data['salary_min'] ?? null, 'max' => $data['salary_max'] ?? null],
            'excluded_employers' => $this->lines($data['excluded_employers'] ?? ''),
        ];
        if ($request->boolean('enabled') && (empty($searchPreferences['target_roles']) || empty($searchPreferences['locations']))) {
            throw ValidationException::withMessages(['enabled' => 'Add at least one target role and location before enabling job search.']);
        }

        $profile = JobProfile::query()->firstOrNew();
        $profile->fill([
            'enabled' => $request->boolean('enabled'),
            'search_preferences' => $searchPreferences,
            'personal_details' => [
                'full_name' => $data['full_name'] ?? null, 'preferred_name' => $data['preferred_name'] ?? null,
                'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null, 'eligibility' => $data['eligibility'] ?? null,
                'availability' => $data['availability'] ?? null,
            ],
            'career_details' => [
                'professional_summary' => $data['professional_summary'] ?? null,
                'experience' => $data['experience'] ?? null, 'education' => $data['education'] ?? null,
                'skills' => $data['skills'] ?? null, 'certifications' => $data['certifications'] ?? null,
                'links' => $data['links'] ?? null, 'languages' => $data['languages'] ?? null,
            ],
            'reusable_answers' => ['notes' => $data['reusable_answers'] ?? null],
            'profile_version' => $profile->exists ? $profile->profile_version + 1 : 1,
        ]);
        $profile->save();

        return redirect()->route('jobs.profile')->with('status', 'Job profile saved.');
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])->map(fn ($line) => trim($line))->filter()->unique()->values()->all();
    }
}
