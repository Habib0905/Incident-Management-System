<?php

namespace Database\Seeders;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $engineer1 = User::create([
            'name' => 'John Engineer',
            'email' => 'john@example.com',
            'password' => 'password',
            'role' => 'engineer',
        ]);

        $engineer2 = User::create([
            'name' => 'Jane Engineer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'role' => 'engineer',
        ]);

        $server1 = Server::create([
            'name' => 'Production Web Server',
            'description' => 'Main production web server',
            'environment' => 'production',
            'api_key' => 'sk_demo_server_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $server2 = Server::create([
            'name' => 'Staging Web Server',
            'description' => 'Staging environment server',
            'environment' => 'staging',
            'api_key' => 'sk_demo_staging_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $server3 = Server::create([
            'name' => 'Development Server',
            'description' => 'Local development server',
            'environment' => 'development',
            'api_key' => 'sk_demo_dev_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->command->info('Demo users created:');
        $this->command->info('  Admin: admin@example.com / password');
        $this->command->info('  Engineer: john@example.com / password');
        $this->command->info('  Engineer: jane@example.com / password');
        $this->command->info('');
        $this->command->info('Demo servers created:');
        $this->command->info('  Production: ' . $server1->api_key);
        $this->command->info('  Staging: ' . $server2->api_key);
        $this->command->info('  Development: ' . $server3->api_key);
    }
}