<?php

namespace App\Http\Controllers;

use App\Models\FeedbackEvent;
use App\Models\StoryCluster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function store(Request $request, string $story): RedirectResponse
    {
        $story = StoryCluster::query()->findOrFail($story);
        $data = $request->validate([
            'relevance_score' => ['required', 'integer', 'between:1,5'],
            'depth_score' => ['required', 'integer', 'between:1,5'],
            'semantic_tags' => ['array', 'max:5'],
            'semantic_tags.*' => [Rule::in(['Great source', 'More like this', 'SEO spam', 'Outdated', 'Paywalled'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        FeedbackEvent::query()->updateOrCreate(
            ['story_cluster_id' => $story->id],
            $data,
        );

        return back()->with('status', 'Feedback saved.');
    }
}
