<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

final class SessionArtifact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'session_id',
        'step',
        'type',
        'content',
        'metadata',
        'status',
        'version'
    ];

    protected $casts = [
        'content' => 'array',
        'metadata' => 'array',
        'version' => 'integer'
    ];

    protected static function booted()
    {
        static::creating(function ($artifact) {
            // Auto-increment version for same session/step
            if (!$artifact->version) {
                $latestVersion = static::where('session_id', $artifact->session_id)
                    ->where('step', $artifact->step)
                    ->max('version') ?? 0;

                $artifact->version = $latestVersion + 1;
            }

            // Set default metadata if not provided
            if (!$artifact->metadata) {
                $artifact->metadata = [
                    'created_at' => now()->toIso8601String(),
                    'created_by' => auth()->id() ?? 'system'
                ];
            }
        });
    }

    /**
     * Get the session this artifact belongs to
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AISession::class, 'session_id');
    }

    /**
     * Get previous versions of this artifact
     */
    public function previousVersions(): HasMany
    {
        return $this->hasMany(static::class, 'session_id')
            ->where('step', $this->step)
            ->where('version', '<', $this->version)
            ->orderBy('version', 'desc');
    }

    /**
     * Create a new version of this artifact
     */
    public function createNewVersion(array $content, array $metadata = []): self
    {
        return static::create([
            'session_id' => $this->session_id,
            'step' => $this->step,
            'type' => $this->type,
            'content' => $content,
            'metadata' => array_merge(
                $metadata,
                ['previous_version' => $this->version]
            ),
            'status' => 'created'
        ]);
    }

    /**
     * Mark artifact as validated
     */
    public function markAsValidated(array $validationData = []): self
    {
        $this->update([
            'status' => 'validated',
            'metadata' => array_merge(
                $this->metadata ?? [],
                [
                    'validated_at' => now()->toIso8601String(),
                    'validation_data' => $validationData
                ]
            )
        ]);

        return $this;
    }

    /**
     * Mark artifact as failed
     */
    public function markAsFailed(string $reason, array $details = []): self
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge(
                $this->metadata ?? [],
                [
                    'failed_at' => now()->toIso8601String(),
                    'failure_reason' => $reason,
                    'failure_details' => $details
                ]
            )
        ]);

        return $this;
    }

    /**
     * Scope query to specific artifact type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope query to specific step
     */
    public function scopeForStep(Builder $query, string $step): Builder
    {
        return $query->where('step', $step);
    }

    /**
     * Scope query to latest versions only
     */
    public function scopeLatestVersions(Builder $query): Builder
    {
        return $query->whereIn('id', function ($query) {
            $query->select(\DB::raw('MAX(id)'))
                ->from('session_artifacts')
                ->groupBy(['session_id', 'step']);
        });
    }

    /**
     * Scope query to validated artifacts
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', 'validated');
    }

    /**
     * Get the artifact content with version history
     */
    public function getContentWithHistory(): array
    {
        return [
            'current' => $this->content,
            'metadata' => $this->metadata,
            'version' => $this->version,
            'history' => $this->previousVersions()
                ->select(['content', 'metadata', 'version', 'created_at'])
                ->get()
                ->toArray()
        ];
    }
}
