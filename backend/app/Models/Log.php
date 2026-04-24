<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'server_id',
        'message',
        'log_level',
        'source',
        'timestamp',
        'raw_payload'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'raw_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function incidents()
    {
        return $this->belongsToMany(Incident::class, 'incident_logs');
    }
}