<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view($request->user() ? 'guide.authenticated' : 'guide.public');
    }
}
