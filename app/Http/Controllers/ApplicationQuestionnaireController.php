<?php

namespace App\Http\Controllers;

use App\Models\ApplicationQuestionnaire;
use App\Models\JobProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationQuestionnaireController extends Controller
{
    public function update(Request $request, string $questionnaire)
    {
        $questionnaire = ApplicationQuestionnaire::query()->with('application')->findOrFail($questionnaire);
        if ($questionnaire->status !== 'pending' || $questionnaire->application->status !== 'needs_information') {
            return redirect()->route('jobs.applications')->withErrors(['questionnaire' => 'This information request is no longer active.']);
        }

        $answers = [];
        $reusable = [];
        foreach ($questionnaire->schema_payload['fields'] ?? [] as $field) {
            $key = 'answers.'.$field['id'];
            $rules = [$field['required'] ? 'required' : 'nullable'];
            $rules = [...$rules, ...match ($field['type']) {
                'email' => ['email', 'max:320'],
                'number' => ['numeric'],
                'date' => ['date'],
                'boolean' => ['boolean'],
                'single_select' => [Rule::in($field['choices'] ?? [])],
                'multi_select' => ['array', 'max:30'],
                'file' => ['file', 'max:5120', 'mimes:pdf,docx'],
                default => ['string', 'max:5000'],
            }];
            $value = validator($request->all(), [$key => $rules])->validate();
            $answer = data_get($value, $key);
            if ($field['type'] === 'multi_select' && is_array($answer)) {
                foreach ($answer as $choice) {
                    if (! in_array($choice, $field['choices'] ?? [], true)) {
                        throw ValidationException::withMessages([$key => 'Choose only one of the provided answers.']);
                    }
                }
            }
            if ($field['type'] === 'file' && $answer) {
                $contents = $answer->get();
                $material = $questionnaire->application->materials()->create([
                    'kind' => 'answer_attachment', 'filename' => $answer->getClientOriginalName(),
                    'mime_type' => $answer->getMimeType(), 'content' => base64_encode($contents),
                    'fact_paths' => [], 'profile_version' => $questionnaire->application->profile_version,
                ]);
                $answer = ['material_id' => $material->id, 'filename' => $material->filename];
            }
            $answers[$field['id']] = $answer;
            if (($field['classification'] ?? 'normal') === 'normal' && $field['type'] !== 'file' && $request->boolean('save.'.$field['id'])) {
                $reusable[$field['id']] = ['label' => $field['label'], 'answer' => $answer];
            }
        }

        DB::transaction(function () use ($questionnaire, $answers, $reusable): void {
            $questionnaire->update(['answer_payload' => $answers, 'status' => 'answered', 'answered_at' => now()]);
            $questionnaire->application->update(['status' => 'ready_to_resume']);
            $questionnaire->application->events()->create(['event' => 'information_answered', 'metadata' => ['questionnaire_id' => $questionnaire->id]]);
            if ($reusable !== []) {
                $profile = JobProfile::query()->firstOrFail();
                $profile->update([
                    'reusable_answers' => array_replace($profile->reusable_answers ?? [], $reusable),
                    'profile_version' => $profile->profile_version + 1,
                ]);
            }
        });

        return redirect()->route('jobs.applications')->with('status', 'Information saved. Codex can resume this application.');
    }
}
