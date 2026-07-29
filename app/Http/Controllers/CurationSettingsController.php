<?php

namespace App\Http\Controllers;

use App\Curation\CurationPolicyService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurationSettingsController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'daily_run_limit' => [
                'required',
                'integer',
                'min:1',
                'max:'.CurationPolicyService::MAX_RUNS_PER_DAY,
            ],
        ]);

        $context->tenant()->update([
            'daily_run_limit' => $data['daily_run_limit'],
        ]);

        return redirect()->route('policy')->with('status', 'Daily curation run limit updated.');
    }
}
