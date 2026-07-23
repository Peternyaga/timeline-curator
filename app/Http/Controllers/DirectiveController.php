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
        $data = $request->validate($this->rules());
        $directive = Directive::query()->create($this->attributes($data));

        return $this->redirectTo($directive, 'Directive added.');
    }

    public function update(Request $request, string $directive): RedirectResponse
    {
        $directive = Directive::query()->findOrFail($directive);
        $directive->update($this->attributes($request->validate($this->rules())));

        return $this->redirectTo($directive, 'Directive updated.');
    }

    public function archive(string $directive): RedirectResponse
    {
        $directive = Directive::query()->findOrFail($directive);
        $directive->update(['enabled' => false]);

        return $this->redirectTo($directive, 'Directive archived.');
    }

    public function restore(string $directive): RedirectResponse
    {
        $directive = Directive::query()->findOrFail($directive);
        if ($directive->expires_at?->isPast()) {
            return redirect()
                ->to(route('policy').'#directive-'.$directive->id)
                ->withErrors(['directive' => 'Update or remove the expired date before restoring this directive.']);
        }

        $directive->update(['enabled' => true]);

        return $this->redirectTo($directive, 'Directive restored.');
    }

    private function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:3000'],
            'strength' => ['required', Rule::in(['hard', 'soft'])],
            'blocked_domains' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    private function attributes(array $data): array
    {
        $blocked = collect(explode(',', (string) ($data['blocked_domains'] ?? '')))
            ->map(fn ($domain) => strtolower(trim($domain)))->filter()->unique()->values()->all();

        return [
            'body' => $data['body'],
            'strength' => $data['strength'],
            'structured_rules' => $blocked === [] ? null : ['blocked_domains' => $blocked],
            // The form collects an inclusive calendar date, so expire the
            // directive at the end of that day rather than at midnight.
            'expires_at' => isset($data['expires_at'])
                ? Carbon::createFromFormat('Y-m-d', $data['expires_at'])->endOfDay()
                : null,
        ];
    }

    private function redirectTo(Directive $directive, string $status): RedirectResponse
    {
        return redirect()
            ->to(route('policy').'#directive-'.$directive->id)
            ->with('status', $status);
    }
}
