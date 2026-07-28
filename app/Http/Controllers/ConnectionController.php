<?php

namespace App\Http\Controllers;

use App\Models\OAuthGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $grants = OAuthGrant::query()
            ->where('user_id', $request->user()->id)
            ->with(['client', 'lastUsedAccessToken'])
            ->latest()
            ->get();

        return view('connections.index', ['grants' => $grants]);
    }

    public function destroy(Request $request, OAuthGrant $grant): RedirectResponse
    {
        abort_unless($grant->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($grant): void {
            $revokedAt = now();
            $grant->update(['revoked_at' => $revokedAt]);
            $grant->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => $revokedAt]);
            $grant->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => $revokedAt]);
        });

        return back()->with('status', 'Timeline connection revoked. That installation must authenticate again.');
    }
}
