<?php

namespace Database\Seeders;

use App\Models\ActivityTimeline;
use App\Models\Incident;
use App\Models\IncidentLog;
use App\Models\Log;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        $prod = Server::create([
            'name' => 'Production Web Server',
            'description' => 'Main production web server',
            'environment' => 'production',
            'api_key' => 'sk_demo_server_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $staging = Server::create([
            'name' => 'Staging Web Server',
            'description' => 'Staging environment server',
            'environment' => 'staging',
            'api_key' => 'sk_demo_staging_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        Server::create([
            'name' => 'Development Server',
            'description' => 'Local development server',
            'environment' => 'development',
            'api_key' => 'sk_demo_dev_123456789012345678901234567890123456789',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $log1 = Log::create([
            'server_id' => $prod->id,
            'message' => 'Connection pool exhausted - max connections reached (20/20)',
            'log_level' => 'error',
            'source' => 'database',
            'timestamp' => now()->subMinutes(30)->utc(),
        ]);

        $log2 = Log::create([
            'server_id' => $prod->id,
            'message' => "Deadlock detected on table 'orders' - transaction rolled back",
            'log_level' => 'error',
            'source' => 'database',
            'timestamp' => now()->subMinutes(25)->utc(),
        ]);

        $log3 = Log::create([
            'server_id' => $prod->id,
            'message' => 'Replication lag exceeded 30s on replica db-replica-02',
            'log_level' => 'error',
            'source' => 'database',
            'timestamp' => now()->subMinutes(20)->utc(),
        ]);

        $log4 = Log::create([
            'server_id' => $prod->id,
            'message' => 'upstream timed out (110: Connection timed out) while connecting to upstream 10.0.0.5:8080',
            'log_level' => 'error',
            'source' => 'nginx',
            'timestamp' => now()->subMinutes(15)->utc(),
        ]);

        $log5 = Log::create([
            'server_id' => $prod->id,
            'message' => 'no live upstreams while connecting to upstream backend_pool',
            'log_level' => 'error',
            'source' => 'nginx',
            'timestamp' => now()->subMinutes(10)->utc(),
        ]);

        $log6 = Log::create([
            'server_id' => $prod->id,
            'message' => 'Brute force detected: 50 failed login attempts from 192.168.1.105',
            'log_level' => 'error',
            'source' => 'auth',
            'timestamp' => now()->subMinutes(5)->utc(),
        ]);

        $log7 = Log::create([
            'server_id' => $staging->id,
            'message' => 'Redis connection timeout after 30s on host cache-primary:6379',
            'log_level' => 'error',
            'source' => 'cache',
            'timestamp' => now()->subMinutes(20)->utc(),
        ]);

        $log8 = Log::create([
            'server_id' => $staging->id,
            'message' => 'Cache eviction spike: 1500 keys evicted in last 60s',
            'log_level' => 'error',
            'source' => 'cache',
            'timestamp' => now()->subMinutes(15)->utc(),
        ]);

        $inc1 = Incident::create([
            'server_id' => $prod->id,
            'title' => 'Connection pool exhausted - max connections reached (20/20)',
            'type' => 'database',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $inc2 = Incident::create([
            'server_id' => $prod->id,
            'title' => 'upstream timed out (110: Connection timed out) while connecting to upstream 10.0.0.5:8080',
            'type' => 'nginx',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $inc3 = Incident::create([
            'server_id' => $prod->id,
            'title' => 'Brute force detected: 50 failed login attempts from 192.168.1.105',
            'type' => 'auth',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $inc4 = Incident::create([
            'server_id' => $staging->id,
            'title' => 'Redis connection timeout after 30s on host cache-primary:6379',
            'type' => 'cache',
            'severity' => 'low',
            'status' => 'open',
        ]);

        IncidentLog::create(['incident_id' => $inc1->id, 'log_id' => $log1->id]);
        IncidentLog::create(['incident_id' => $inc1->id, 'log_id' => $log2->id]);
        IncidentLog::create(['incident_id' => $inc1->id, 'log_id' => $log3->id]);

        IncidentLog::create(['incident_id' => $inc2->id, 'log_id' => $log4->id]);
        IncidentLog::create(['incident_id' => $inc2->id, 'log_id' => $log5->id]);

        IncidentLog::create(['incident_id' => $inc3->id, 'log_id' => $log6->id]);

        IncidentLog::create(['incident_id' => $inc4->id, 'log_id' => $log7->id]);
        IncidentLog::create(['incident_id' => $inc4->id, 'log_id' => $log8->id]);

        ActivityTimeline::create([
            'incident_id' => $inc1->id,
            'event_type' => 'created',
            'note' => 'Incident created',
            'created_at' => $inc1->created_at,
        ]);

        ActivityTimeline::create([
            'incident_id' => $inc2->id,
            'event_type' => 'created',
            'note' => 'Incident created',
            'created_at' => $inc2->created_at,
        ]);

        ActivityTimeline::create([
            'incident_id' => $inc3->id,
            'event_type' => 'created',
            'note' => 'Incident created',
            'created_at' => $inc3->created_at,
        ]);

        ActivityTimeline::create([
            'incident_id' => $inc4->id,
            'event_type' => 'created',
            'note' => 'Incident created',
            'created_at' => $inc4->created_at,
        ]);

        $this->command->info('Demo users created:');
        $this->command->info('  Admin: admin@example.com / password');
        $this->command->info('  Engineer: john@example.com / password');
        $this->command->info('  Engineer: jane@example.com / password');
        $this->command->info('');
        $this->command->info('Demo servers created:');
        $this->command->info('  Production: ' . $prod->api_key);
        $this->command->info('  Staging: ' . $staging->api_key);
        $this->command->info('  Development: ' . Server::where('environment', 'development')->first()->api_key);
        $this->command->info('');
        $this->command->info('Demo incidents created:');
        $this->command->info('  [critical] database - Connection pool exhausted (3 logs)');
        $this->command->info('  [high]     nginx - upstream timed out (2 logs)');
        $this->command->info('  [high]     auth - Brute force detected (1 log)');
        $this->command->info('  [low]      cache - Redis connection timeout (2 logs)');
    }
}
