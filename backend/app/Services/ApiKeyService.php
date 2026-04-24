<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function generateKey(): string
    {
        return 'sk_' . Str::random(60);
    }

    public function validateKey(string $key, Server $server): bool
    {
        return $server->api_key === $key && $server->is_active;
    }

    public function regenerateKey(Server $server): string
    {
        $newKey = $this->generateKey();
        $server->api_key = $newKey;
        $server->save();
        
        return $newKey;
    }

    public function revokeKey(Server $server): void
    {
        $server->is_active = false;
        $server->save();
    }

    public function activateKey(Server $server): void
    {
        $server->is_active = true;
        $server->save();
    }
}