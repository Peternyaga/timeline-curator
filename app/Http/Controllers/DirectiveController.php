<?php

namespace App\Http\Controllers;

use App\Models\Directive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DirectiveController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
            'strength' => ['required', Rule::in(['hard', 'soft'])],
            'blocked_domains' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        $blocked = collect(explode(',', (string) ($data['blocked_domains'] ?? '')))
            ->map(fn ($domain) => strtolower(trim($domain)))->filter()->unique()->values()->all();
        Directive::query()->create([
            'body' => $data['body'],
            'strength' => $data['strength'],
            'structured_rules' => $blocked === [] ? null : ['blocked_domains' => $blocked],
            // The form collects an inclusive calendar date, so expire the
            // directive at the end of that day rather than at midnight.
            'expires_at' => isset($data['expires_at'])
                ? Carbon::createFromFormat('Y-m-d', $data['expires_at'])->endOfDay()
                : null,
        ]);

        return back()->with('status', 'Directive added.');
    }
}
