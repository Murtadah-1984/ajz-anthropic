<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\AssistantRoleFactory;
use Carbon\Carbon;

final class AssistantRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'role_name',
        'xml_config',
        'prompt',
        'xml_output',
        'metadata',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $dates = [
        'last_used_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected static function newFactory(): AssistantRoleFactory
    {
        return AssistantRoleFactory::new();
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(AssistantOutput::class);
    }

    public function highPerformingOutputs(): HasMany
    {
        return $this->outputs()
            ->where('feedback_score', '>=', 4)
            ->orderBy('feedback_score', 'desc');
    }

    public function recentOutputs(): HasMany
    {
        return $this->outputs()
            ->where('generated_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('generated_at', 'desc');
    }

    public function averageFeedbackScore(): float
    {
        return $this->outputs()->avg('feedback_score') ?? 0.0;
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => Carbon::now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $roleName)
    {
        return $query->where('role_name', $roleName);
    }
}
