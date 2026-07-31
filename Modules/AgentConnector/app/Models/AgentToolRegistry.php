<?php

namespace Modules\AgentConnector\Models;

use Illuminate\Database\Eloquent\Model;

class AgentToolRegistry extends Model
{
    protected $table = 'agent_tool_registries';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'capabilities',
        'endpoint',
        'order',
        'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('order');
    }
}
