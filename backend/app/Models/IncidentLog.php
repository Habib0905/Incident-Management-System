<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentLog extends Model
{
    protected $fillable = [
        'incident_id',
        'log_id'
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function log()
    {
        return $this->belongsTo(Log::class);
    }
}