<?php

namespace App\Http\Controllers;

use App\Auth\Auth0WebClient;
use App\Auth\UserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Auth0WebClient $client): RedirectResponse
    {
        return redirect()->away($client->make()->login(route('auth.callback')));
    }

    public function callback(Auth0WebClient $client, UserProvisioner $provisioner): RedirectResponse
    {
        $auth0 = $client->make();
        $auth0->exchange(route('auth.callback'));
        $claims = (array) ($auth0->getCredentials()?->user ?? []);
        Auth::login($provisioner->fromClaims($claims), remember: true);

        return redirect()->route('timeline');
    }

    public function logout(Auth0WebClient $client): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->away($client->make()->logout(route('home')));
    }
}
