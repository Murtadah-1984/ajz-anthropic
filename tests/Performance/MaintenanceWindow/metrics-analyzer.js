const MetricsStore = require('./metrics-store');
const stats = require('simple-statistics');

/**
 * Performance metrics analyzer
 */
class MetricsAnalyzer {
    constructor(metricsStore) {
        this.store = metricsStore;
    }

    /**
     * Analyze performance trends
     */
    async analyzeTrends(type, name, timeRange = '7d') {
        const data = await this.store.getTimeSeries(type, name, '1h', timeRange);
        const values = data.map(d => d.average);

        return {
            trend: this.calculateTrend(values),
            seasonality: this.detectSeasonality(values),
            anomalies: this.detectAnomalies(values),
            forecast: this.generateForecast(values)
        };
    }

    /**
     * Calculate performance trend
     */
    calculateTrend(values) {
        const n = values.length;
        if (n < 2) return { slope: 0, correlation: 0 };

        const x = Array.from({ length: n }, (_, i) => i);
        const regression = stats.linearRegression(x.map(i => [i, values[i]]));
        const correlation = stats.sampleCorrelation(x, values);

        return {
            slope: regression.m,
            intercept: regression.b,
            correlation,
            direction: regression.m > 0 ? 'increasing' : 'decreasing',
            strength: Math.abs(correlation) > 0.7 ? 'strong' : 'weak'
        };
    }

    /**
     * Detect seasonality in data
     */
    detectSeasonality(values) {
        // Analyze daily patterns (24 points for hourly data)
        const dailyPattern = this.analyzePattern(values, 24);

        // Analyze weekly patterns (168 points for hourly data)
        const weeklyPattern = this.analyzePattern(values, 168);

        return {
            daily: dailyPattern,
            weekly: weeklyPattern,
            hasSeasonality: dailyPattern.strength > 0.6 || weeklyPattern.strength > 0.6
        };
    }

    /**
     * Analyze pattern in data
     */
    analyzePattern(values, period) {
        if (values.length < period * 2) {
            return { strength: 0, pattern: [] };
        }

        // Calculate average pattern
        const pattern = Array(period).fill(0);
        const counts = Array(period).fill(0);

        for (let i = 0; i < values.length; i++) {
            const index = i % period;
            pattern[index] += values[i];
            counts[index]++;
        }

        const avgPattern = pattern.map((sum, i) => sum / counts[i]);

        // Calculate pattern strength (correlation between actual and pattern)
        const patternValues = [];
        for (let i = 0; i < values.length; i++) {
            patternValues.push(avgPattern[i % period]);
        }

        const correlation = stats.sampleCorrelation(values, patternValues);

        return {
            strength: Math.abs(correlation),
            pattern: avgPattern
        };
    }

    /**
     * Detect anomalies in data
     */
    detectAnomalies(values) {
        const mean = stats.mean(values);
        const stdDev = stats.standardDeviation(values);
        const threshold = 2; // Number of standard deviations

        const anomalies = values.map((value, index) => {
            const zScore = Math.abs(value - mean) / stdDev;
            return zScore > threshold ? {
                index,
                value,
                zScore,
                deviation: value - mean
            } : null;
        }).filter(Boolean);

        return {
            anomalies,
            count: anomalies.length,
            percentage: (anomalies.length / values.length) * 100
        };
    }

    /**
     * Generate performance forecast
     */
    generateForecast(values, periods = 24) {
        if (values.length < 2) return [];

        // Simple exponential smoothing
        const alpha = 0.3; // Smoothing factor
        const forecast = [];
        let lastValue = values[values.length - 1];

        for (let i = 0; i < periods; i++) {
            lastValue = alpha * values[values.length - 1] + (1 - alpha) * lastValue;
            forecast.push(lastValue);
        }

        return {
            values: forecast,
            confidence: this.calculateConfidenceIntervals(values, forecast)
        };
    }

    /**
     * Calculate confidence intervals
     */
    calculateConfidenceIntervals(historical, forecast) {
        const errors = [];
        for (let i = 1; i < historical.length; i++) {
            errors.push(historical[i] - historical[i - 1]);
        }

        const errorStdDev = stats.standardDeviation(errors);
        const confidence = 1.96 * errorStdDev; // 95% confidence interval

        return forecast.map(value => ({
            lower: value - confidence,
            upper: value + confidence
        }));
    }

    /**
     * Analyze performance patterns
     */
    async analyzePatterns(type, name, timeRange = '30d') {
        const data = await this.store.getTimeSeries(type, name, '1h', timeRange);
        const values = data.map(d => d.average);

        return {
            patterns: this.findPatterns(values),
            cycles: this.detectCycles(values),
            correlations: await this.findCorrelations(type, name, timeRange)
        };
    }

    /**
     * Find patterns in data
     */
    findPatterns(values) {
        const patterns = [];
        const minPatternLength = 3;
        const maxPatternLength = Math.floor(values.length / 2);

        for (let len = minPatternLength; len <= maxPatternLength; len++) {
            const potentialPatterns = this.findRepeatingSequences(values, len);
            patterns.push(...potentialPatterns);
        }

        return patterns.sort((a, b) => b.occurrences - a.occurrences).slice(0, 5);
    }

    /**
     * Find repeating sequences
     */
    findRepeatingSequences(values, length) {
        const sequences = new Map();
        const tolerance = 0.1; // 10% variation allowed

        for (let i = 0; i <= values.length - length; i++) {
            const sequence = values.slice(i, i + length);
            const key = sequence.map(v => Math.round(v / tolerance) * tolerance).join(',');

            if (!sequences.has(key)) {
                sequences.set(key, {
                    sequence,
                    positions: [],
                    occurrences: 0
                });
            }

            sequences.get(key).positions.push(i);
            sequences.get(key).occurrences++;
        }

        return Array.from(sequences.values())
            .filter(s => s.occurrences > 1);
    }

    /**
     * Detect cycles in data
     */
    detectCycles(values) {
        const cycles = [];
        const maxPeriod = Math.floor(values.length / 2);

        for (let period = 2; period <= maxPeriod; period++) {
            const correlation = this.calculateCycleStrength(values, period);
            if (correlation > 0.6) {
                cycles.push({
                    period,
                    strength: correlation
                });
            }
        }

        return cycles.sort((a, b) => b.strength - a.strength);
    }

    /**
     * Calculate cycle strength
     */
    calculateCycleStrength(values, period) {
        const segments = [];
        for (let i = 0; i < values.length - period; i += period) {
            segments.push(values.slice(i, i + period));
        }

        if (segments.length < 2) return 0;

        const correlations = [];
        for (let i = 1; i < segments.length; i++) {
            correlations.push(
                stats.sampleCorrelation(segments[i - 1], segments[i])
            );
        }

        return stats.mean(correlations);
    }

    /**
     * Find correlations with other metrics
     */
    async findCorrelations(type, name, timeRange) {
        const targetData = await this.store.getTimeSeries(type, name, '1h', timeRange);
        const targetValues = targetData.map(d => d.average);

        const allMetrics = await this.store.all(`
            SELECT DISTINCT metric_type, metric_name
            FROM metrics
            WHERE timestamp >= datetime('now', ?)
        `, [this.store.getTimeConstraint(timeRange)]);

        const correlations = [];

        for (const metric of allMetrics) {
            if (metric.metric_type === type && metric.metric_name === name) continue;

            const data = await this.store.getTimeSeries(
                metric.metric_type,
                metric.metric_name,
                '1h',
                timeRange
            );

            const values = data.map(d => d.average);
            if (values.length === targetValues.length) {
                const correlation = stats.sampleCorrelation(targetValues, values);
                correlations.push({
                    metric_type: metric.metric_type,
                    metric_name: metric.metric_name,
                    correlation: correlation
                });
            }
        }

        return correlations
            .filter(c => Math.abs(c.correlation) > 0.5)
            .sort((a, b) => Math.abs(b.correlation) - Math.abs(a.correlation));
    }

    /**
     * Generate performance report
     */
    async generateReport(timeRange = '7d') {
        const metrics = await this.store.all(`
            SELECT DISTINCT metric_type, metric_name
            FROM metrics
            WHERE timestamp >= datetime('now', ?)
        `, [this.store.getTimeConstraint(timeRange)]);

        const report = {
            timeRange,
            timestamp: new Date().toISOString(),
            metrics: []
        };

        for (const metric of metrics) {
            const analysis = await this.analyzeTrends(
                metric.metric_type,
                metric.metric_name,
                timeRange
            );

            const stats = await this.store.getMetricStats(
                metric.metric_type,
                metric.metric_name,
                timeRange
            );

            const patterns = await this.analyzePatterns(
                metric.metric_type,
                metric.metric_name,
                timeRange
            );

            report.metrics.push({
                type: metric.metric_type,
                name: metric.metric_name,
                stats,
                analysis,
                patterns
            });
        }

        return report;
    }
}

module.exports = MetricsAnalyzer;
