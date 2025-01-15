import type { BenchmarkStats } from './guards.benchmark';

/**
 * Performance trend prediction utilities
 */
class PerformancePredictor {
    /**
     * Predict future performance metrics
     */
    predictMetrics(
        snapshots: BenchmarkStats[],
        predictionSteps: number = 5
    ): {
        predictions: BenchmarkStats[];
        confidence: number;
        anomalies: AnomalyDetection[];
    } {
        const timeSeriesData = this.prepareTimeSeriesData(snapshots);
        const predictions = this.generatePredictions(timeSeriesData, predictionSteps);
        const confidence = this.calculateConfidence(timeSeriesData, predictions);
        const anomalies = this.detectAnomalies(timeSeriesData);

        return {
            predictions,
            confidence,
            anomalies
        };
    }

    /**
     * Prepare time series data for analysis
     */
    private prepareTimeSeriesData(snapshots: BenchmarkStats[]): TimeSeriesData {
        return {
            executionTime: snapshots.map(s => ({
                mean: s.executionTime.mean,
                p95: s.executionTime.p95
            })),
            memory: snapshots.map(s => ({
                mean: s.memory.mean,
                peak: s.memory.peak,
                growth: s.memory.growth
            })),
            gc: snapshots.map(s => ({
                pauses: s.gc.totalPauses,
                meanPause: s.gc.meanPause
            }))
        };
    }

    /**
     * Generate performance predictions
     */
    private generatePredictions(
        data: TimeSeriesData,
        steps: number
    ): BenchmarkStats[] {
        const predictions: BenchmarkStats[] = [];

        for (let i = 0; i < steps; i++) {
            const prediction = this.predictNextSnapshot(data, i);
            predictions.push(prediction);

            // Update time series with prediction for next iteration
            data.executionTime.push({
                mean: prediction.executionTime.mean,
                p95: prediction.executionTime.p95
            });
            data.memory.push({
                mean: prediction.memory.mean,
                peak: prediction.memory.peak,
                growth: prediction.memory.growth
            });
            data.gc.push({
                pauses: prediction.gc.totalPauses,
                meanPause: prediction.gc.meanPause
            });
        }

        return predictions;
    }

    /**
     * Predict next performance snapshot
     */
    private predictNextSnapshot(data: TimeSeriesData, step: number): BenchmarkStats {
        return {
            executionTime: {
                mean: this.predictValue(data.executionTime.map(t => t.mean), step),
                median: this.predictValue(data.executionTime.map(t => t.mean), step), // Use mean as approximation
                p95: this.predictValue(data.executionTime.map(t => t.p95), step),
                min: 0, // Not predicted
                max: 0  // Not predicted
            },
            memory: {
                mean: this.predictValue(data.memory.map(m => m.mean), step),
                peak: this.predictValue(data.memory.map(m => m.peak), step),
                growth: this.predictValue(data.memory.map(m => m.growth), step)
            },
            gc: {
                totalPauses: Math.round(this.predictValue(data.gc.map(g => g.pauses), step)),
                meanPause: this.predictValue(data.gc.map(g => g.meanPause), step),
                maxPause: 0 // Not predicted
            }
        };
    }

    /**
     * Predict single value using exponential smoothing
     */
    private predictValue(values: number[], step: number): number {
        const alpha = 0.3; // Smoothing factor
        const beta = 0.1;  // Trend smoothing factor

        // Initialize level and trend
        let level = values[0];
        let trend = values[1] - values[0];

        // Update level and trend
        for (let i = 1; i < values.length; i++) {
            const oldLevel = level;
            level = alpha * values[i] + (1 - alpha) * (level + trend);
            trend = beta * (level - oldLevel) + (1 - beta) * trend;
        }

        // Predict future value
        return level + (step + 1) * trend;
    }

    /**
     * Calculate prediction confidence
     */
    private calculateConfidence(
        actual: TimeSeriesData,
        predictions: BenchmarkStats[]
    ): number {
        const errors: number[] = [];

        // Calculate prediction errors for historical data
        for (let i = 1; i < actual.executionTime.length; i++) {
            const predicted = this.predictValue(
                actual.executionTime.slice(0, i).map(t => t.mean),
                0
            );
            const actualValue = actual.executionTime[i].mean;
            errors.push(Math.abs((predicted - actualValue) / actualValue));
        }

        // Calculate confidence based on mean absolute percentage error
        const mape = errors.reduce((sum, error) => sum + error, 0) / errors.length;
        return Math.max(0, 1 - mape);
    }

    /**
     * Detect performance anomalies
     */
    private detectAnomalies(data: TimeSeriesData): AnomalyDetection[] {
        const anomalies: AnomalyDetection[] = [];
        const metrics = [
            {
                name: 'Execution Time',
                values: data.executionTime.map(t => t.mean)
            },
            {
                name: 'Memory Usage',
                values: data.memory.map(m => m.mean)
            },
            {
                name: 'GC Pauses',
                values: data.gc.map(g => g.pauses)
            }
        ];

        for (const metric of metrics) {
            const { mean, stdDev } = this.calculateStats(metric.values);
            const threshold = 2; // Number of standard deviations for anomaly

            metric.values.forEach((value, index) => {
                const zScore = Math.abs((value - mean) / stdDev);
                if (zScore > threshold) {
                    anomalies.push({
                        metric: metric.name,
                        index,
                        value,
                        zScore,
                        severity: this.calculateSeverity(zScore)
                    });
                }
            });
        }

        return anomalies;
    }

    /**
     * Calculate basic statistics
     */
    private calculateStats(values: number[]): { mean: number; stdDev: number } {
        const mean = values.reduce((a, b) => a + b, 0) / values.length;
        const variance = values.reduce((sum, val) =>
            sum + Math.pow(val - mean, 2), 0
        ) / values.length;
        return {
            mean,
            stdDev: Math.sqrt(variance)
        };
    }

    /**
     * Calculate anomaly severity
     */
    private calculateSeverity(zScore: number): 'low' | 'medium' | 'high' {
        if (zScore > 4) return 'high';
        if (zScore > 3) return 'medium';
        return 'low';
    }

    /**
     * Generate prediction report
     */
    generateReport(
        predictions: BenchmarkStats[],
        confidence: number,
        anomalies: AnomalyDetection[]
    ): string {
        return `
Performance Prediction Report
===========================

Prediction Confidence: ${(confidence * 100).toFixed(1)}%

Predicted Trends
---------------
Execution Time: ${this.formatTrend(predictions.map(p => p.executionTime.mean))}
Memory Usage: ${this.formatTrend(predictions.map(p => p.memory.mean))}
GC Activity: ${this.formatTrend(predictions.map(p => p.gc.totalPauses))}

Detected Anomalies
-----------------
${anomalies.map(a => this.formatAnomaly(a)).join('\n')}

Recommendations
--------------
${this.generateRecommendations(predictions, anomalies)}
`;
    }

    /**
     * Format trend description
     */
    private formatTrend(values: number[]): string {
        const start = values[0];
        const end = values[values.length - 1];
        const change = ((end - start) / start) * 100;
        const direction = change > 0 ? 'increase' : 'decrease';
        return `${Math.abs(change).toFixed(1)}% ${direction} predicted`;
    }

    /**
     * Format anomaly description
     */
    private formatAnomaly(anomaly: AnomalyDetection): string {
        return `${anomaly.metric}: Anomaly detected (z-score: ${anomaly.zScore.toFixed(2)}, severity: ${anomaly.severity})`;
    }

    /**
     * Generate recommendations based on predictions
     */
    private generateRecommendations(
        predictions: BenchmarkStats[],
        anomalies: AnomalyDetection[]
    ): string {
        const recommendations: string[] = [];

        // Check for concerning trends
        const lastPrediction = predictions[predictions.length - 1];
        const firstPrediction = predictions[0];

        if (lastPrediction.executionTime.mean > firstPrediction.executionTime.mean * 1.2) {
            recommendations.push(
                'Performance degradation predicted. Consider optimizing critical paths.'
            );
        }

        if (lastPrediction.memory.growth > firstPrediction.memory.growth * 1.1) {
            recommendations.push(
                'Increasing memory growth trend detected. Monitor for potential memory leaks.'
            );
        }

        if (lastPrediction.gc.totalPauses > firstPrediction.gc.totalPauses * 1.15) {
            recommendations.push(
                'GC activity expected to increase. Consider implementing object pooling.'
            );
        }

        // Add recommendations based on anomalies
        const highSeverityAnomalies = anomalies.filter(a => a.severity === 'high');
        if (highSeverityAnomalies.length > 0) {
            recommendations.push(
                'Critical anomalies detected. Immediate investigation recommended.'
            );
        }

        if (recommendations.length === 0) {
            recommendations.push(
                'No significant performance issues predicted. Continue monitoring.'
            );
        }

        return recommendations.join('\n');
    }
}

interface TimeSeriesData {
    executionTime: Array<{
        mean: number;
        p95: number;
    }>;
    memory: Array<{
        mean: number;
        peak: number;
        growth: number;
    }>;
    gc: Array<{
        pauses: number;
        meanPause: number;
    }>;
}

interface AnomalyDetection {
    metric: string;
    index: number;
    value: number;
    zScore: number;
    severity: 'low' | 'medium' | 'high';
}

export { PerformancePredictor };
