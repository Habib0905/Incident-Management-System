<?php

namespace App\Providers;

use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::viaRequest('api-key', function ($request) {
            $token = $request->bearerToken();

            if (!$token) {
                return null;
            }

            $server = Server::where('api_key', $token)
                ->where('is_active', true)
                ->first();

            return $server;
        });
    }
}