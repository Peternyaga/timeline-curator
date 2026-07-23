<?php

namespace App\Http\Controllers;

use App\Models\Directive;
use App\Models\Topic;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function __invoke(): View
    {
        $topics = Topic::query()->orderBy('name')->get();
        $directives = Directive::query()->latest()->get();

        return view('policy', [
            'activeTopics' => $topics->where('active', true),
            'archivedTopics' => $topics->where('active', false),
            'activeDirectives' => $directives->where('enabled', true),
            'archivedDirectives' => $directives->where('enabled', false),
            'topicPresets' => config('policy_catalog.topics', []),
            'directivePresets' => config('policy_catalog.directives', []),
        ]);
    }
}
