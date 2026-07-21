<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DeploymentController extends Controller
{
    public function show(): Response
    {
        $this->assertAvailable();

        return $this->html(<<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Timeline deployment</title></head>
<body style="max-width:620px;margin:10vh auto;padding:24px;font:16px/1.55 system-ui;color:#16211b;background:#f4f1e9">
<h1>Install Timeline Curator</h1>
<p>This one-time installer creates or updates the configured database. Enter the deployment token generated with the release package.</p>
<form method="post" action="/deployment/install">
<label for="token">Deployment token</label><br>
<input id="token" name="token" type="password" required autocomplete="off" style="width:100%;padding:12px;margin:8px 0 16px">
<button type="submit" style="padding:12px 18px;background:#173f2b;color:white;border:0">Run database installation</button>
</form>
</body></html>
HTML);
    }

    public function install(Request $request): Response
    {
        $this->assertAvailable();

        $token = (string) $request->input('token');
        $expectedHash = (string) config('deployment.web_installer_token_hash');
        if ($token === '' || $expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $token))) {
            abort(403, 'The deployment token is invalid.');
        }

        $inProgressPath = storage_path('app/deployment-installing.lock');
        $handle = @fopen($inProgressPath, 'x');
        if ($handle === false) {
            abort(409, 'A deployment is already running.');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            File::put($this->completedPath(), json_encode([
                'installed_at' => now()->toIso8601String(),
                'app_url' => config('app.url'),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $this->html(<<<'HTML'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installation complete</title></head>
<body style="max-width:620px;margin:10vh auto;padding:24px;font:16px/1.55 system-ui;color:#16211b;background:#f4f1e9">
<h1>Database installation complete</h1>
<p>Set <code>WEB_INSTALLER_ENABLED=false</code> in the server's <code>.env</code>, then open the application health endpoint and configure Auth0.</p>
<p><a href="/up">Check application health</a></p>
</body></html>
HTML);
        } catch (Throwable $exception) {
            report($exception);

            return $this->html('<h1>Installation failed</h1><p>Check the database values and the server error log, then try again.</p>', 500);
        } finally {
            fclose($handle);
            File::delete($inProgressPath);
        }
    }

    private function assertAvailable(): void
    {
        abort_unless(config('deployment.web_installer_enabled'), 404);
        abort_if(File::exists($this->completedPath()), 404);
    }

    private function completedPath(): string
    {
        return storage_path('app/deployment-installed.lock');
    }

    private function html(string $body, int $status = 200): Response
    {
        return response($body, $status)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
