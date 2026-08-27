<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentInstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $completedPath;

    private string $inProgressPath;

    private string $updateTokenHashPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->completedPath = storage_path('app/deployment-installed.lock');
        $this->inProgressPath = storage_path('app/deployment-installing.lock');
        $this->updateTokenHashPath = storage_path('app/deployment-update-token-hash');
        File::delete([$this->completedPath, $this->inProgressPath, $this->updateTokenHashPath]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->completedPath, $this->inProgressPath, $this->updateTokenHashPath]);
        parent::tearDown();
    }

    public function test_installer_is_hidden_when_disabled(): void
    {
        config()->set('deployment.web_installer_enabled', false);

        $this->get('/deployment/install')->assertNotFound();
    }

    public function test_installer_rejects_an_invalid_token(): void
    {
        config()->set('deployment.web_installer_enabled', true);
        config()->set('deployment.web_installer_token_hash', hash('sha256', 'correct-token'));

        $this->post('/deployment/install', ['token' => 'wrong-token'])->assertForbidden();
        $this->assertFileDoesNotExist($this->completedPath);
    }

    public function test_installer_migrates_once_and_then_hides_itself(): void
    {
        config()->set('deployment.web_installer_enabled', true);
        config()->set('deployment.web_installer_token_hash', hash('sha256', 'correct-token'));

        $this->post('/deployment/install', ['token' => 'correct-token'])
            ->assertOk()
            ->assertSee('Database installation complete');

        $this->assertFileExists($this->completedPath);
        $this->get('/deployment/install')->assertNotFound();
    }

    public function test_installer_can_set_the_existing_owners_first_local_password(): void
    {
        config()->set('deployment.web_installer_enabled', true);
        config()->set('deployment.web_installer_token_hash', hash('sha256', 'correct-token'));
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => null,
            'auth0_sub' => 'auth0|legacy-owner',
        ]);

        $this->post('/deployment/install', [
            'token' => 'correct-token',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'new-local-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-local-password', $user->fresh()->password));
        $this->assertSame($user->tenant_id, $user->fresh()->tenant_id);
    }

    public function test_one_use_update_token_runs_migrations_after_initial_install(): void
    {
        config()->set('deployment.web_installer_enabled', false);
        File::put($this->completedPath, '{}');
        File::put($this->updateTokenHashPath, hash('sha256', 'update-token'));

        $this->get('/deployment/install')
            ->assertOk()
            ->assertSee('Update Timeline Curator')
            ->assertDontSee('Existing owner migration');

        $this->post('/deployment/install', ['token' => 'update-token'])
            ->assertOk()
            ->assertSee('Database update complete');

        $this->assertFileDoesNotExist($this->updateTokenHashPath);
        $this->assertFileExists($this->completedPath);
        $this->get('/deployment/install')->assertNotFound();
    }

    public function test_new_views_can_render_before_the_previous_route_cache_is_cleared(): void
    {
        $viewPaths = [
            resource_path('views/welcome.blade.php'),
            resource_path('views/auth/register.blade.php'),
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/timeline.blade.php'),
        ];

        foreach ($viewPaths as $viewPath) {
            $this->assertStringNotContainsString(
                "route('guide')",
                File::get($viewPath),
                "{$viewPath} must not require the new named route while an older route cache may still be active.",
            );
        }
    }

    public function test_web_installer_does_not_rebuild_caches_from_the_booted_release(): void
    {
        $controller = File::get(app_path('Http/Controllers/DeploymentController.php'));

        $this->assertStringContainsString("Artisan::call('optimize:clear');", $controller);
        $this->assertStringNotContainsString("Artisan::call('optimize');", $controller);
    }
}
