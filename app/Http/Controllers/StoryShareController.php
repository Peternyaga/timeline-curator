<?php

namespace App\Http\Controllers;

use App\Models\StoryCluster;
use App\Sharing\StoryShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryShareController extends Controller
{
    public function store(
        Request $request,
        StoryCluster $story,
        StoryShareService $shares,
    ): JsonResponse {
        $share = $shares->createOrReuse($story, $request->user());

        return response()->json([
            'message' => $share->wasRecentlyCreated ? 'Public share link created.' : 'Public share link ready.',
            'share' => $shares->presentation($share),
        ], $share->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(
        StoryCluster $story,
        StoryShareService $shares,
    ): JsonResponse {
        $revoked = $shares->revoke($story);

        return response()->json([
            'message' => $revoked ? 'Public share link disabled.' : 'No active public share link exists.',
            'revoked' => $revoked,
        ]);
    }
}
