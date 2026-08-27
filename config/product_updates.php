<?php

return [
    'current_plugin_version' => '0.5.0',
    'minimum_plugin_version' => '0.4.0',
    'items' => [
        [
            'id' => '2026-08-job-search-companion',
            'version' => '0.5.0',
            'published_at' => '2026-08-26T00:00:00+03:00',
            'title' => 'Your job search companion',
            'summary' => 'Create a private job profile, rate verified matches, and approve one application at a time for Codex to process.',
            'action_label' => 'Set up Jobs',
            'action_route' => 'jobs.profile',
        ],
        [
            'id' => '2026-07-durable-connections',
            'version' => '0.4.0',
            'published_at' => '2026-07-28T00:00:00+03:00',
            'title' => 'More reliable scheduled curation',
            'summary' => 'Timeline connections now renew in the background until you revoke them, and scheduled authentication failures are reported clearly.',
            'action_label' => 'Review your connections',
            'action_route' => 'connections.index',
        ],
        [
            'id' => '2026-07-guided-visual-sharing',
            'version' => '0.3.0',
            'published_at' => '2026-07-24T00:00:00+03:00',
            'title' => 'Visual stories, guided policies, and sharing',
            'summary' => 'Create policies from presets, receive media-rich stories, and share permanent public snapshots with rich social previews.',
            'action_label' => 'Explore your timeline',
            'action_route' => 'timeline',
        ],
    ],
];
