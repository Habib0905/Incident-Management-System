<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentView extends Model
{
    protected $fillable = [
        'incident_id',
        'user_id',
        'viewed_at'
    ];

    public $timestamps = false;

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}