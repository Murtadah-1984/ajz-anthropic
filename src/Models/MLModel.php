<?php

namespace Ajz\Anthropic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MLModel extends Model
{
    protected $table = 'ml_models';

    protected $fillable = [
        'type',
        'configuration',
        'estimator',
        'metrics'
    ];

    protected $casts = [
        'configuration' => 'array',
        'metrics' => 'array'
    ];

    /**
     * Get the predictions for this model.
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(MLPrediction::class, 'model_id');
    }

    /**
     * Get the metrics for this model.
     */
    public function metricHistory(): HasMany
    {
        return $this->hasMany(MLMetric::class, 'model_id');
    }

    /**
     * Get the latest metrics for this model.
     */
    public function getLatestMetricsAttribute(): ?array
    {
        return $this->metricHistory()->latest()->first()?->metrics;
    }

    /**
     * Get the prediction success rate.
     */
    public function getSuccessRateAttribute(): float
    {
        $metrics = $this->metrics ?? [];
        return $metrics['accuracy'] ?? 0.0;
    }

    /**
     * Get the model version.
     */
    public function getVersionAttribute(): int
    {
        $metrics = $this->metrics ?? [];
        return $metrics['training_iterations'] ?? 0;
    }

    /**
     * Check if the model needs retraining.
     */
    public function needsRetraining(): bool
    {
        $metrics = $this->metrics ?? [];
        $accuracy = $metrics['accuracy'] ?? 0.0;
        $threshold = config('anthropic.ml.retraining_threshold', 0.8);

        return $accuracy < $threshold;
    }

    /**
     * Get the model status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->needsRetraining()) {
            return 'needs_training';
        }

        $metrics = $this->metrics ?? [];
        $accuracy = $metrics['accuracy'] ?? 0.0;

        if ($accuracy >= 0.95) {
            return 'excellent';
        } elseif ($accuracy >= 0.85) {
            return 'good';
        } elseif ($accuracy >= 0.75) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get the model performance trend.
     */
    public function getPerformanceTrendAttribute(): array
    {
        return $this->metricHistory()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($metric) => [
                'timestamp' => $metric->created_at,
                'accuracy' => $metric->metrics['accuracy'] ?? 0.0,
                'f1_score' => $metric->metrics['f1_score'] ?? 0.0
            ])
            ->toArray();
    }

    /**
     * Get recent predictions.
     */
    public function getRecentPredictionsAttribute(): array
    {
        return $this->predictions()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($prediction) => [
                'timestamp' => $prediction->created_at,
                'input' => $prediction->input,
                'output' => $prediction->output,
                'confidence' => $prediction->confidence
            ])
            ->toArray();
    }
}
