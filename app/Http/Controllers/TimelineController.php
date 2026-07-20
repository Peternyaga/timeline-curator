<?php

namespace App\Http\Controllers;

use App\Models\Directive;
use App\Models\StoryCluster;
use App\Models\Topic;

class TimelineController extends Controller
{
    public function __invoke()
    {
        return view('timeline', [
            'stories' => StoryCluster::query()->with(['sources', 'feedback'])->latest('published_at')->paginate(20),
            'topics' => Topic::query()->orderBy('name')->get(),
            'directives' => Directive::query()->latest()->get(),
            'semanticTags' => ['Great source', 'More like this', 'SEO spam', 'Outdated', 'Paywalled'],
        ]);
    }
}
