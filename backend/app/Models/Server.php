<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $table = 'servers';
    
    protected $fillable = [
        'name',
        'description',
        'environment',
        'api_key',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}