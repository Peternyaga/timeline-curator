<?php

namespace Tests\Feature;

use App\Curation\CurationException;
use App\Curation\CurationIngestionService;
use App\Curation\CurationPolicyService;
use App\Jobs\JobApplicationService;
use App\Jobs\JobCurationService;
use App\Models\JobApplication;
use App\Models\JobCuration;
use App\Models\JobFeedbackEvent;
use App\Models\JobProfile;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobCompanionTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_migration_resumes_after_a_partial_ddl_failure(): void
    {
        foreach ([
            'application_events', 'application_questionnaires', 'application_materials',
            'job_applications', 'job_feedback_events', 'job_sources', 'job_curations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $migration = require database_path('migrations/2026_08_26_000900_create_job_companion_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('agent_runs', ['job_queries', 'job_accepted_count', 'job_rejected_count']));
        $this->assertTrue(Schema::hasTable('job_profiles'));
        $this->assertTrue(Schema::hasTable('job_profile_documents'));
        $this->assertTrue(Schema::hasTable('job_curations'));
        $this->assertTrue(Schema::hasTable('application_events'));
    }

    public function test_profile_is_guided_encrypted_and_has_separate_readiness_states(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);

        $this->actingAs($user)->put('/jobs/profile', [
            'enabled' => 1,
            'target_roles' => "Product manager\nProgramme manager",
            'locations' => "Nairobi\nRemote",
            'work_modes' => ['remote', 'hybrid'],
            'full_name' => 'Private Candidate',
            'email' => 'candidate@example.com',
            'phone' => '+254700000000',
            'experience' => 'Led verified programmes.',
            'skills' => 'Product strategy',
        ])->assertRedirect(route('jobs.profile'));

        $this->setTenant($user);
        $profile = JobProfile::query()->firstOrFail();
        $this->assertTrue($profile->enabled);
        $this->assertTrue($profile->searchReady());
        $this->assertFalse($profile->applicationReady());
        $this->assertSame(['Product manager', 'Programme manager'], $profile->search_preferences['target_roles']);
        $this->assertStringNotContainsString('Private Candidate', DB::table('job_profiles')->value('personal_details'));
    }

    public function test_jobs_are_validated_deduplicated_and_feedback_is_updated_in_place(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        Topic::query()->create(['name' => 'Technology', 'brief' => 'Verified news']);
        $profile = $this->profile();
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['technology news'], '0.5.0', ['product manager Nairobi']);

        $first = app(JobCurationService::class)->submit($run, $context['context_version'], [$this->jobPayload()]);
        $this->assertCount(1, $first['accepted']);
        $jobId = $first['accepted'][0]['job_id'];

        $secondRun = app(CurationIngestionService::class)->begin($context['context_version'], ['technology follow-up'], '0.5.0', ['product manager Kenya']);
        $duplicate = app(JobCurationService::class)->submit($secondRun, $context['context_version'], [[...$this->jobPayload(), 'client_item_id' => 'job-2']]);
        $this->assertSame('duplicate', $duplicate['rejected'][0]['code']);

        $this->actingAs($user)->postJson("/jobs/{$jobId}/feedback", [
            'interest_score' => 5, 'match_score' => 4,
            'semantic_tags' => ['strong-fit'], 'comment' => 'Good role.',
        ])->assertOk();
        $this->actingAs($user)->postJson("/jobs/{$jobId}/feedback", [
            'interest_score' => 4, 'match_score' => 5,
            'semantic_tags' => ['accurate-match'], 'comment' => 'Updated.',
        ])->assertOk();

        $this->setTenant($user);
        $this->assertSame($jobId, JobCuration::query()->forJobsWorkspace()->firstOrFail()->id);
        $this->assertSame(1, JobFeedbackEvent::query()->count());
        $this->assertSame(5, JobFeedbackEvent::query()->firstOrFail()->match_score);
        $this->assertTrue($profile->searchReady());
    }

    public function test_approval_claim_materials_questionnaire_and_confirmed_outcome_are_audited(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->setTenant($user);
        Topic::query()->create(['name' => 'Technology', 'brief' => 'Verified news']);
        $profile = $this->profile();
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['technology'], '0.5.0', ['product jobs']);
        $result = app(JobCurationService::class)->submit($run, $context['context_version'], [$this->jobPayload()]);
        $job = JobCuration::query()->findOrFail($result['accepted'][0]['job_id']);

        $service = app(JobApplicationService::class);
        $application = $service->approve($job, $profile);
        $claim = $service->claim('attempt-one');
        $this->assertSame($application->id, $claim['application_id']);
        $this->assertArrayNotHasKey('tenant_id', $claim['listing']);
        $repeatClaim = $service->claim('attempt-one');
        $this->assertTrue($repeatClaim['idempotent']);
        $this->assertSame($claim['claim_token'], $repeatClaim['claim_token']);

        $saved = $service->saveMaterials($application->fresh(), $claim['claim_token'], [[
            'kind' => 'cover_letter', 'mime_type' => 'text/plain',
            'content' => 'A truthful tailored letter.', 'fact_paths' => ['career_details.skills'],
        ]]);
        $this->assertCount(1, $saved);

        $questionnaire = $service->requestInformation($application->fresh(), $claim['claim_token'], [[
            'id' => 'notice-period', 'label' => 'What is your notice period?',
            'type' => 'short_text', 'classification' => 'normal', 'required' => true,
        ]]);
        $this->actingAs($user)->put(route('jobs.questionnaires.update', $questionnaire), [
            'answers' => ['notice-period' => 'One month'],
            'save' => ['notice-period' => 1],
        ])->assertRedirect(route('jobs.applications'));

        $this->setTenant($user);
        $resumed = $service->claim('attempt-two');
        $this->expectException(CurationException::class);
        $service->recordOutcome(JobApplication::query()->findOrFail($resumed['application_id']), $resumed['claim_token'], 'submitted');
    }

    public function test_submitted_outcome_requires_and_retains_channel_specific_evidence(): void
    {
        $user = User::factory()->create();
        $this->setTenant($user);
        Topic::query()->create(['name' => 'Technology', 'brief' => 'Verified news']);
        $profile = $this->profile();
        $context = app(CurationPolicyService::class)->context();
        $run = app(CurationIngestionService::class)->begin($context['context_version'], ['technology'], '0.5.0', ['product jobs']);
        $result = app(JobCurationService::class)->submit($run, $context['context_version'], [$this->jobPayload()]);
        $job = JobCuration::query()->findOrFail($result['accepted'][0]['job_id']);
        $service = app(JobApplicationService::class);
        $application = $service->approve($job, $profile);
        $claim = $service->claim('confirmed-attempt');
        $completed = $service->recordOutcome($application->fresh(), $claim['claim_token'], 'submitted', [
            'confirmation_url' => 'https://jobs.example.com/applications/confirmed',
            'confirmation_reference' => 'ABC-123',
            'confirmed_at' => now()->toIso8601String(),
        ]);

        $this->assertSame('submitted', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame('outcome_recorded', $completed->events()->latest()->firstOrFail()->event);
    }

    private function profile(): JobProfile
    {
        return JobProfile::query()->create([
            'enabled' => true,
            'search_preferences' => ['target_roles' => ['Product manager'], 'locations' => ['Nairobi'], 'work_modes' => ['hybrid']],
            'personal_details' => ['full_name' => 'Candidate', 'email' => 'candidate@example.com', 'phone' => '+254700000000'],
            'career_details' => ['experience' => 'Led product teams.', 'skills' => 'Product strategy'],
            'reusable_answers' => [],
        ]);
    }

    private function jobPayload(): array
    {
        return [
            'client_item_id' => 'job-1', 'title' => 'Product Manager', 'employer' => 'Example Ltd',
            'canonical_url' => 'https://jobs.example.com/product-manager',
            'application_url' => 'https://jobs.example.com/product-manager/apply', 'application_channel' => 'web',
            'location' => 'Nairobi, Kenya', 'workplace_type' => 'hybrid', 'employment_type' => 'full-time',
            'posted_at' => '2026-08-25T09:00:00+03:00', 'deadline_at' => '2026-09-30T23:59:59+03:00',
            'summary_points' => ['Lead a product portfolio.'], 'requirements' => ['Product experience.'],
            'match_points' => ['Matches product leadership experience.'], 'gaps' => ['Confirm sector preference.'],
            'sources' => [[
                'title' => 'Original listing', 'url' => 'https://jobs.example.com/product-manager',
                'role' => 'primary', 'published_at' => '2026-08-25T09:00:00+03:00',
            ]],
            'feedback_tags' => [
                ['id' => 'strong-fit', 'label' => 'Strong role fit', 'signal' => 'more_like_this'],
                ['id' => 'accurate-match', 'label' => 'Accurate match', 'signal' => 'accurate_match'],
                ['id' => 'less-like-this', 'label' => 'Fewer roles like this', 'signal' => 'less_like_this'],
                ['id' => 'wrong-fit', 'label' => 'Fit was inaccurate', 'signal' => 'inaccurate_match'],
            ],
        ];
    }

    private function setTenant(User $user): void
    {
        app(TenantContext::class)->set(Tenant::query()->findOrFail($user->tenant_id));
    }
}
