<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\AssistantOutputFactory;

final class AssistantOutput extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assistant_role_id',
        'output',
        'feedback_score',
        'metadata',
        'generated_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'feedback_score' => 'integer',
        'generated_at' => 'datetime'
    ];

    protected static function newFactory(): AssistantOutputFactory
    {
        return AssistantOutputFactory::new();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AssistantRole::class, 'assistant_role_id');
    }

    public function scopeHighPerforming($query)
    {
        return $query->where('feedback_score', '>=', 4);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('generated_at', '>=', Carbon::now()->subDays($days));
    }
}
