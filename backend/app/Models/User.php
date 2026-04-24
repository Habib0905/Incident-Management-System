<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function servers()
    {
        return $this->hasMany(Server::class, 'created_by');
    }

    public function assignedIncidents()
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityTimeline::class);
    }

    public function viewedIncidents()
    {
        return $this->belongsToMany(Incident::class, 'incident_views')
                    ->withPivot('viewed_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEngineer(): bool
    {
        return $this->role === 'engineer';
    }
}