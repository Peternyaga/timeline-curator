<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentInstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $completedPath;

    private string $inProgressPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->completedPath = storage_path('app/deployment-installed.lock');
        $this->inProgressPath = storage_path('app/deployment-installing.lock');
        File::delete([$this->completedPath, $this->inProgressPath]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->completedPath, $this->inProgressPath]);
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
}
