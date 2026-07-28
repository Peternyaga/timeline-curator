<?php

namespace App\Http\Controllers;

use App\Support\ProductUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductUpdateController extends Controller
{
    public function index(Request $request, ProductUpdateService $updates): View
    {
        return view('updates.index', [
            'updates' => $updates->allFor($request->user()),
        ]);
    }

    public function read(Request $request, string $update, ProductUpdateService $updates): RedirectResponse
    {
        abort_unless($updates->exists($update), 404);
        $updates->markRead($request->user(), $update);

        return back()->with('status', 'Update marked as read.');
    }

    public function readAll(Request $request, ProductUpdateService $updates): RedirectResponse
    {
        $updates->markAllRead($request->user());

        return back()->with('status', 'All updates marked as read.');
    }
}
