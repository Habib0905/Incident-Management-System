<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'server_id',
        'created_by',
        'assigned_to',
        'title',
        'type',
        'severity',
        'status',
        'summary'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function logs()
    { 
        return $this->belongsToMany(Log::class, 'incident_logs');
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityTimeline::class);
    }

    public function views()
    {
        return $this->hasMany(IncidentView::class);
    }
}