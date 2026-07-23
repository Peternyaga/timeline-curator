<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $this->ensureTopicCapacity();
        $topic = Topic::query()->create($data);
        $request->user()->update(['onboarding_complete' => true]);

        return $this->redirectTo($topic, 'Topic added.');
    }

    public function update(Request $request, string $topic): RedirectResponse
    {
        $topic = Topic::query()->findOrFail($topic);
        $topic->update($request->validate($this->rules()));

        return $this->redirectTo($topic, 'Topic updated.');
    }

    public function archive(string $topic): RedirectResponse
    {
        $topic = Topic::query()->findOrFail($topic);
        $topic->update(['active' => false]);

        return $this->redirectTo($topic, 'Topic archived.');
    }

    public function restore(string $topic): RedirectResponse
    {
        $topic = Topic::query()->findOrFail($topic);
        if (! $topic->active) {
            $this->ensureTopicCapacity();
            $topic->update(['active' => true]);
        }

        return $this->redirectTo($topic, 'Topic restored.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'brief' => ['required', 'string', 'max:3000'],
        ];
    }

    private function ensureTopicCapacity(): void
    {
        if (Topic::query()->where('active', true)->count() >= 5) {
            throw ValidationException::withMessages([
                'topic' => 'The beta supports five active topics. Archive one before adding or restoring another.',
            ]);
        }
    }

    private function redirectTo(Topic $topic, string $status): RedirectResponse
    {
        return redirect()
            ->to(route('policy').'#topic-'.$topic->id)
            ->with('status', $status);
    }
}
