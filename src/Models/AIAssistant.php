<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class AIAssistant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'assistant_role_id',
        'team_id',
        'user_id',
        'configuration',
        'memory',
        'capabilities',
        'is_personal',
        'is_active'
    ];

    protected $casts = [
        'configuration' => 'array',
        'memory' => 'array',
        'capabilities' => 'array',
        'is_personal' => 'boolean',
        'is_active' => 'boolean',
        'last_interaction' => 'datetime'
    ];

    public function role()
    {
        return $this->belongsTo(AssistantRole::class, 'assistant_role_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function delegatedTasks(): HasMany
    {
        return $this->hasMany(TaskDelegation::class, 'from_assistant_id');
    }

    public function receivedTasks(): HasMany
    {
        return $this->hasMany(TaskDelegation::class, 'to_assistant_id');
    }

    // Helper methods for capability checking
    public function canHandle(string $taskType): bool
    {
        return in_array($taskType, $this->capabilities['supported_tasks'] ?? []);
    }

    public function updateMemory(array $data): void
    {
        $this->memory = array_merge($this->memory ?? [], $data);
        $this->save();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePersonal($query)
    {
        return $query->where('is_personal', true);
    }

    public function scopeTeamAssistants($query)
    {
        return $query->where('is_personal', false)->whereNotNull('team_id');
    }
}
