import type { BenchmarkStats } from './guards.benchmark';
import type { PredictionIntervals, ReliabilityAnalysis } from './guards.confidence';

/**
 * Performance data validation
 */
class DataValidator {
    /**
     * Validate all performance data
     */
    validateData(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[],
        reliability: ReliabilityAnalysis
    ): ValidationResult {
        const results: ValidationError[] = [
            ...this.validateMetadata(name),
            ...this.validateHistorical(historical),
            ...this.validatePredictions(predictions, historical.length),
            ...this.validateIntervals(intervals, predictions.length),
            ...this.validateReliability(reliability)
        ];

        return {
            valid: results.length === 0,
            errors: results,
            warnings: this.generateWarnings(historical, predictions, intervals)
        };
    }

    /**
     * Validate metadata
     */
    private validateMetadata(name: string): ValidationError[] {
        const errors: ValidationError[] = [];

        if (!name || typeof name !== 'string') {
            errors.push({
                type: 'metadata',
                severity: 'error',
                message: 'Name must be a non-empty string'
            });
        }

        return errors;
    }

    /**
     * Validate historical data
     */
    private validateHistorical(historical: BenchmarkStats[]): ValidationError[] {
        const errors: ValidationError[] = [];

        if (!Array.isArray(historical)) {
            errors.push({
                type: 'historical',
                severity: 'error',
                message: 'Historical data must be an array'
            });
            return errors;
        }

        if (historical.length === 0) {
            errors.push({
                type: 'historical',
                severity: 'error',
                message: 'Historical data cannot be empty'
            });
            return errors;
        }

        historical.forEach((stat, index) => {
            if (!this.isValidBenchmarkStat(stat)) {
                errors.push({
                    type: 'historical',
                    severity: 'error',
                    message: `Invalid benchmark stat at index ${index}`
                });
            }

            if (this.hasAnomalousValues(stat)) {
                errors.push({
                    type: 'historical',
                    severity: 'warning',
                    message: `Potentially anomalous values at index ${index}`
                });
            }
        });

        return errors;
    }

    /**
     * Validate predictions
     */
    private validatePredictions(
        predictions: BenchmarkStats[],
        historicalLength: number
    ): ValidationError[] {
        const errors: ValidationError[] = [];

        if (!Array.isArray(predictions)) {
            errors.push({
                type: 'predictions',
                severity: 'error',
                message: 'Predictions must be an array'
            });
            return errors;
        }

        if (predictions.length === 0) {
            errors.push({
                type: 'predictions',
                severity: 'error',
                message: 'Predictions cannot be empty'
            });
            return errors;
        }

        if (predictions.length > historicalLength * 2) {
            errors.push({
                type: 'predictions',
                severity: 'warning',
                message: 'Number of predictions exceeds twice the historical data points'
            });
        }

        predictions.forEach((stat, index) => {
            if (!this.isValidBenchmarkStat(stat)) {
                errors.push({
                    type: 'predictions',
                    severity: 'error',
                    message: `Invalid prediction stat at index ${index}`
                });
            }

            if (this.hasUnrealisticPrediction(stat)) {
                errors.push({
                    type: 'predictions',
                    severity: 'warning',
                    message: `Potentially unrealistic prediction at index ${index}`
                });
            }
        });

        return errors;
    }

    /**
     * Validate confidence intervals
     */
    private validateIntervals(
        intervals: PredictionIntervals[],
        predictionsLength: number
    ): ValidationError[] {
        const errors: ValidationError[] = [];

        if (!Array.isArray(intervals)) {
            errors.push({
                type: 'intervals',
                severity: 'error',
                message: 'Intervals must be an array'
            });
            return errors;
        }

        if (intervals.length !== predictionsLength) {
            errors.push({
                type: 'intervals',
                severity: 'error',
                message: 'Number of intervals must match number of predictions'
            });
        }

        intervals.forEach((interval, index) => {
            if (!this.isValidInterval(interval)) {
                errors.push({
                    type: 'intervals',
                    severity: 'error',
                    message: `Invalid interval at index ${index}`
                });
            }

            if (this.hasInvalidBounds(interval)) {
                errors.push({
                    type: 'intervals',
                    severity: 'error',
                    message: `Invalid bounds at index ${index}`
                });
            }

            if (this.hasWideBounds(interval)) {
                errors.push({
                    type: 'intervals',
                    severity: 'warning',
                    message: `Unusually wide confidence bounds at index ${index}`
                });
            }
        });

        return errors;
    }

    /**
     * Validate reliability analysis
     */
    private validateReliability(reliability: ReliabilityAnalysis): ValidationError[] {
        const errors: ValidationError[] = [];

        if (!reliability || typeof reliability !== 'object') {
            errors.push({
                type: 'reliability',
                severity: 'error',
                message: 'Reliability analysis must be an object'
            });
            return errors;
        }

        if (!reliability.trends || typeof reliability.trends !== 'object') {
            errors.push({
                type: 'reliability',
                severity: 'error',
                message: 'Reliability trends must be an object'
            });
        }

        if (!Array.isArray(reliability.recommendations)) {
            errors.push({
                type: 'reliability',
                severity: 'error',
                message: 'Recommendations must be an array'
            });
        }

        // Validate trend values
        const metrics = ['executionTime', 'memory', 'gc'];
        metrics.forEach(metric => {
            if (typeof reliability.trends[metric] !== 'number') {
                errors.push({
                    type: 'reliability',
                    severity: 'error',
                    message: `Invalid ${metric} trend value`
                });
            }

            if (reliability.trends[metric] > 1) {
                errors.push({
                    type: 'reliability',
                    severity: 'warning',
                    message: `Unusually high ${metric} trend value`
                });
            }
        });

        return errors;
    }

    /**
     * Generate warnings based on data patterns
     */
    private generateWarnings(
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[]
    ): ValidationWarning[] {
        const warnings: ValidationWarning[] = [];

        // Check for sudden changes in historical data
        const historicalChanges = this.detectSuddenChanges(historical);
        if (historicalChanges.length > 0) {
            warnings.push({
                type: 'historical',
                message: 'Sudden changes detected in historical data',
                details: historicalChanges
            });
        }

        // Check prediction stability
        const stabilityIssues = this.checkPredictionStability(predictions);
        if (stabilityIssues.length > 0) {
            warnings.push({
                type: 'predictions',
                message: 'Potential stability issues in predictions',
                details: stabilityIssues
            });
        }

        // Check confidence interval consistency
        const intervalIssues = this.checkIntervalConsistency(intervals);
        if (intervalIssues.length > 0) {
            warnings.push({
                type: 'intervals',
                message: 'Inconsistent confidence intervals detected',
                details: intervalIssues
            });
        }

        return warnings;
    }

    /**
     * Detect sudden changes in data
     */
    private detectSuddenChanges(data: BenchmarkStats[]): string[] {
        const changes: string[] = [];
        const threshold = 0.5; // 50% change

        for (let i = 1; i < data.length; i++) {
            const metrics = ['executionTime', 'memory', 'gc'];
            metrics.forEach(metric => {
                const previous = data[i - 1][metric].mean;
                const current = data[i][metric].mean;
                const change = Math.abs((current - previous) / previous);

                if (change > threshold) {
                    changes.push(
                        `${metric}: ${(change * 100).toFixed(1)}% change at index ${i}`
                    );
                }
            });
        }

        return changes;
    }

    /**
     * Check prediction stability
     */
    private checkPredictionStability(predictions: BenchmarkStats[]): string[] {
        const issues: string[] = [];
        const metrics = ['executionTime', 'memory', 'gc'];

        metrics.forEach(metric => {
            const values = predictions.map(p => p[metric].mean);
            const variance = this.calculateVariance(values);
            const mean = values.reduce((a, b) => a + b, 0) / values.length;
            const cv = Math.sqrt(variance) / mean; // Coefficient of variation

            if (cv > 0.5) {
                issues.push(
                    `High variability in ${metric} predictions (CV: ${cv.toFixed(2)})`
                );
            }
        });

        return issues;
    }

    /**
     * Check confidence interval consistency
     */
    private checkIntervalConsistency(intervals: PredictionIntervals[]): string[] {
        const issues: string[] = [];
        const metrics = ['executionTime', 'memory', 'gc'];

        metrics.forEach(metric => {
            const widths = intervals.map(i =>
                i[metric].upper - i[metric].lower
            );
            const meanWidth = widths.reduce((a, b) => a + b, 0) / widths.length;
            const variance = this.calculateVariance(widths);
            const cv = Math.sqrt(variance) / meanWidth;

            if (cv > 0.3) {
                issues.push(
                    `Inconsistent ${metric} interval widths (CV: ${cv.toFixed(2)})`
                );
            }
        });

        return issues;
    }

    /**
     * Utility functions
     */
    private isValidBenchmarkStat(stat: any): boolean {
        return (
            stat &&
            typeof stat === 'object' &&
            this.isValidMetric(stat.executionTime) &&
            this.isValidMetric(stat.memory) &&
            this.isValidMetric(stat.gc)
        );
    }

    private isValidMetric(metric: any): boolean {
        return (
            metric &&
            typeof metric === 'object' &&
            typeof metric.mean === 'number' &&
            !isNaN(metric.mean) &&
            metric.mean >= 0
        );
    }

    private isValidInterval(interval: any): boolean {
        return (
            interval &&
            typeof interval === 'object' &&
            this.isValidBounds(interval.executionTime) &&
            this.isValidBounds(interval.memory) &&
            this.isValidBounds(interval.gc)
        );
    }

    private isValidBounds(bounds: any): boolean {
        return (
            bounds &&
            typeof bounds === 'object' &&
            typeof bounds.lower === 'number' &&
            typeof bounds.upper === 'number' &&
            !isNaN(bounds.lower) &&
            !isNaN(bounds.upper)
        );
    }

    private hasInvalidBounds(interval: PredictionIntervals): boolean {
        const metrics = ['executionTime', 'memory', 'gc'];
        return metrics.some(metric =>
            interval[metric].lower > interval[metric].upper ||
            interval[metric].lower < 0
        );
    }

    private hasWideBounds(interval: PredictionIntervals): boolean {
        const metrics = ['executionTime', 'memory', 'gc'];
        return metrics.some(metric => {
            const width = interval[metric].upper - interval[metric].lower;
            const mean = (interval[metric].upper + interval[metric].lower) / 2;
            return width > mean * 2; // More than 200% of mean
        });
    }

    private hasAnomalousValues(stat: BenchmarkStats): boolean {
        const metrics = ['executionTime', 'memory', 'gc'];
        return metrics.some(metric =>
            stat[metric].mean === 0 ||
            stat[metric].mean === Infinity ||
            stat[metric].mean > 1e6 // Unreasonably large value
        );
    }

    private hasUnrealisticPrediction(stat: BenchmarkStats): boolean {
        const metrics = ['executionTime', 'memory', 'gc'];
        return metrics.some(metric =>
            stat[metric].mean < 0 ||
            stat[metric].mean === Infinity ||
            stat[metric].mean > 1e6
        );
    }

    private calculateVariance(values: number[]): number {
        const mean = values.reduce((a, b) => a + b, 0) / values.length;
        return values.reduce((sum, val) =>
            sum + Math.pow(val - mean, 2), 0
        ) / values.length;
    }
}

interface ValidationError {
    type: 'metadata' | 'historical' | 'predictions' | 'intervals' | 'reliability';
    severity: 'error' | 'warning';
    message: string;
}

interface ValidationWarning {
    type: 'historical' | 'predictions' | 'intervals';
    message: string;
    details: string[];
}

interface ValidationResult {
    valid: boolean;
    errors: ValidationError[];
    warnings: ValidationWarning[];
}

export { DataValidator, type ValidationResult, type ValidationError, type ValidationWarning };
