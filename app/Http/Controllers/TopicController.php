<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if(Topic::query()->where('active', true)->count() >= 5, 422, 'The beta supports five active topics.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'brief' => ['required', 'string', 'max:3000']]);
        Topic::query()->create($data);
        $request->user()->update(['onboarding_complete' => true]);

        return back()->with('status', 'Topic added.');
    }
}
