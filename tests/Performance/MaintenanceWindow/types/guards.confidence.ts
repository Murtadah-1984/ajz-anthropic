import type { BenchmarkStats } from './guards.benchmark';

/**
 * Confidence interval calculations for performance predictions
 */
class ConfidenceCalculator {
    /**
     * Calculate confidence intervals for predictions
     */
    calculateIntervals(
        snapshots: BenchmarkStats[],
        predictions: BenchmarkStats[],
        confidenceLevel: number = 0.95
    ): PredictionIntervals[] {
        return predictions.map((prediction, index) => ({
            executionTime: this.calculateMetricIntervals(
                snapshots.map(s => s.executionTime.mean),
                prediction.executionTime.mean,
                index + 1,
                confidenceLevel
            ),
            memory: this.calculateMetricIntervals(
                snapshots.map(s => s.memory.mean),
                prediction.memory.mean,
                index + 1,
                confidenceLevel
            ),
            gc: this.calculateMetricIntervals(
                snapshots.map(s => s.gc.totalPauses),
                prediction.gc.totalPauses,
                index + 1,
                confidenceLevel
            )
        }));
    }

    /**
     * Calculate confidence intervals for a single metric
     */
    private calculateMetricIntervals(
        historicalValues: number[],
        predictedValue: number,
        predictionStep: number,
        confidenceLevel: number
    ): MetricInterval {
        // Calculate prediction error variance
        const errors = this.calculatePredictionErrors(historicalValues);
        const errorVariance = this.calculateVariance(errors);

        // Calculate standard error of prediction
        const standardError = Math.sqrt(errorVariance * (1 + predictionStep / historicalValues.length));

        // Calculate critical value for desired confidence level
        const criticalValue = this.getCriticalValue(confidenceLevel);

        // Calculate margin of error
        const marginOfError = criticalValue * standardError;

        return {
            lower: predictedValue - marginOfError,
            upper: predictedValue + marginOfError,
            standardError,
            confidenceLevel
        };
    }

    /**
     * Calculate prediction errors using rolling forecasts
     */
    private calculatePredictionErrors(values: number[]): number[] {
        const errors: number[] = [];
        const minDataPoints = 3; // Minimum points needed for prediction

        for (let i = minDataPoints; i < values.length; i++) {
            const historical = values.slice(0, i);
            const actual = values[i];
            const predicted = this.predictNextValue(historical);
            errors.push(actual - predicted);
        }

        return errors;
    }

    /**
     * Predict next value using simple exponential smoothing
     */
    private predictNextValue(values: number[]): number {
        const alpha = 0.3; // Smoothing factor
        let level = values[0];

        for (let i = 1; i < values.length; i++) {
            level = alpha * values[i] + (1 - alpha) * level;
        }

        return level;
    }

    /**
     * Calculate variance of a set of values
     */
    private calculateVariance(values: number[]): number {
        const mean = values.reduce((a, b) => a + b, 0) / values.length;
        return values.reduce((sum, val) =>
            sum + Math.pow(val - mean, 2), 0
        ) / (values.length - 1); // Use n-1 for sample variance
    }

    /**
     * Get critical value for confidence level
     * Using normal distribution approximation
     */
    private getCriticalValue(confidenceLevel: number): number {
        // Map common confidence levels to z-scores
        const zScores: { [key: number]: number } = {
            0.99: 2.576,
            0.95: 1.96,
            0.90: 1.645,
            0.85: 1.44,
            0.80: 1.28
        };

        return zScores[confidenceLevel] || 1.96; // Default to 95% confidence
    }

    /**
     * Generate confidence interval report
     */
    generateReport(intervals: PredictionIntervals[]): string {
        return `
Confidence Interval Analysis
==========================

Execution Time Predictions
-------------------------
${this.formatIntervalSeries(intervals.map(i => i.executionTime), 'ms')}

Memory Usage Predictions
----------------------
${this.formatIntervalSeries(intervals.map(i => i.memory), 'MB')}

GC Activity Predictions
---------------------
${this.formatIntervalSeries(intervals.map(i => i.gc), 'pauses')}

Interpretation Guide
------------------
- The intervals represent the range where we expect the true values to fall
- Wider intervals indicate higher uncertainty in the predictions
- Consider the confidence level when making decisions based on these predictions
- Watch for trends in interval widths as they indicate prediction stability
`;
    }

    /**
     * Format interval series for reporting
     */
    private formatIntervalSeries(
        intervals: MetricInterval[],
        unit: string
    ): string {
        return intervals.map((interval, index) => `
Step ${index + 1}:
  Range: ${interval.lower.toFixed(2)} - ${interval.upper.toFixed(2)} ${unit}
  Confidence: ${(interval.confidenceLevel * 100).toFixed(0)}%
  Standard Error: ±${interval.standardError.toFixed(2)} ${unit}
  Interval Width: ${(interval.upper - interval.lower).toFixed(2)} ${unit}
`).join('\n');
    }

    /**
     * Analyze prediction reliability
     */
    analyzeReliability(intervals: PredictionIntervals[]): ReliabilityAnalysis {
        const widths = {
            executionTime: intervals.map(i => i.executionTime.upper - i.executionTime.lower),
            memory: intervals.map(i => i.memory.upper - i.memory.lower),
            gc: intervals.map(i => i.gc.upper - i.gc.lower)
        };

        const trends = {
            executionTime: this.calculateWidthTrend(widths.executionTime),
            memory: this.calculateWidthTrend(widths.memory),
            gc: this.calculateWidthTrend(widths.gc)
        };

        return {
            trends,
            recommendations: this.generateReliabilityRecommendations(trends)
        };
    }

    /**
     * Calculate trend in interval widths
     */
    private calculateWidthTrend(widths: number[]): number {
        if (widths.length < 2) return 0;
        const firstWidth = widths[0];
        const lastWidth = widths[widths.length - 1];
        return (lastWidth - firstWidth) / firstWidth;
    }

    /**
     * Generate reliability recommendations
     */
    private generateReliabilityRecommendations(trends: {
        executionTime: number;
        memory: number;
        gc: number;
    }): string[] {
        const recommendations: string[] = [];

        if (trends.executionTime > 0.2) {
            recommendations.push(
                'Execution time predictions show increasing uncertainty. Consider collecting more samples.'
            );
        }

        if (trends.memory > 0.2) {
            recommendations.push(
                'Memory usage predictions becoming less reliable. Review memory usage patterns.'
            );
        }

        if (trends.gc > 0.2) {
            recommendations.push(
                'GC prediction uncertainty increasing. Monitor GC behavior more closely.'
            );
        }

        if (recommendations.length === 0) {
            recommendations.push(
                'Prediction reliability is stable. Continue with current monitoring approach.'
            );
        }

        return recommendations;
    }
}

interface MetricInterval {
    lower: number;
    upper: number;
    standardError: number;
    confidenceLevel: number;
}

interface PredictionIntervals {
    executionTime: MetricInterval;
    memory: MetricInterval;
    gc: MetricInterval;
}

interface ReliabilityAnalysis {
    trends: {
        executionTime: number;
        memory: number;
        gc: number;
    };
    recommendations: string[];
}

export { ConfidenceCalculator, type PredictionIntervals, type ReliabilityAnalysis };
