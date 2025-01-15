import * as Types from './index';

/**
 * Type guard utilities for Performance Maintenance Window Tools
 */

// Common Type Guards
export function isTimeRange(value: unknown): value is Types.TimeRange {
    const validRanges = ['1h', '6h', '12h', '24h', '7d', '30d'];
    return typeof value === 'string' && validRanges.includes(value);
}

export function isMetricType(value: unknown): value is Types.MetricType {
    const validTypes = ['accuracy', 'performance', 'stability', 'reliability'];
    return typeof value === 'string' && validTypes.includes(value);
}

export function isPriority(value: unknown): value is Types.Priority {
    const validPriorities = ['low', 'medium', 'high'];
    return typeof value === 'string' && validPriorities.includes(value);
}

// Base Type Guards
export function isTrend(value: unknown): value is Types.Trend {
    if (!value || typeof value !== 'object') return false;
    const trend = value as Types.Trend;
    return (
        typeof trend.slope === 'number' &&
        typeof trend.intercept === 'number' &&
        typeof trend.correlation === 'number' &&
        typeof trend.significance === 'number'
    );
}

export function isConfidenceInterval(value: unknown): value is Types.ConfidenceInterval {
    if (!value || typeof value !== 'object') return false;
    const ci = value as Types.ConfidenceInterval;
    return (
        typeof ci.lower === 'number' &&
        typeof ci.upper === 'number' &&
        typeof ci.confidence === 'number'
    );
}

// Analysis Type Guards
export function isAnomaly(value: unknown): value is Types.Anomaly {
    if (!value || typeof value !== 'object') return false;
    const anomaly = value as Types.Anomaly;
    return (
        typeof anomaly.index === 'number' &&
        typeof anomaly.value === 'number' &&
        typeof anomaly.score === 'number' &&
        ['spike', 'dip', 'shift'].includes(anomaly.type)
    );
}

export function isMemoryTrends(value: unknown): value is Types.MemoryTrends {
    if (!value || typeof value !== 'object') return false;
    const trends = value as Types.MemoryTrends;
    return (
        isTrend(trends.trend) &&
        isGrowthMetrics(trends.growth) &&
        typeof trends.leakProbability === 'number' &&
        isFragmentationMetrics(trends.fragmentation)
    );
}

export function isGrowthMetrics(value: unknown): value is Types.GrowthMetrics {
    if (!value || typeof value !== 'object') return false;
    const metrics = value as Types.GrowthMetrics;
    return (
        typeof metrics.rate === 'number' &&
        ['linear', 'exponential', 'logarithmic'].includes(metrics.pattern) &&
        typeof metrics.stability === 'number'
    );
}

export function isFragmentationMetrics(value: unknown): value is Types.FragmentationMetrics {
    if (!value || typeof value !== 'object') return false;
    const metrics = value as Types.FragmentationMetrics;
    return (
        typeof metrics.ratio === 'number' &&
        isTrend(metrics.trend) &&
        typeof metrics.impact === 'number'
    );
}

export function isResourceTrends(value: unknown): value is Types.ResourceTrends {
    if (!value || typeof value !== 'object') return false;
    const trends = value as Types.ResourceTrends;
    return (
        isResourceMetrics(trends.cpu) &&
        isResourceMetrics(trends.memory) &&
        typeof trends.correlation === 'number' &&
        isResourceSaturation(trends.saturation)
    );
}

export function isResourceMetrics(value: unknown): value is Types.ResourceMetrics {
    if (!value || typeof value !== 'object') return false;
    const metrics = value as Types.ResourceMetrics;
    return (
        typeof metrics.utilization === 'number' &&
        isTrend(metrics.trend) &&
        Array.isArray(metrics.bottlenecks) &&
        metrics.bottlenecks.every(isBottleneck)
    );
}

export function isBottleneck(value: unknown): value is Types.Bottleneck {
    if (!value || typeof value !== 'object') return false;
    const bottleneck = value as Types.Bottleneck;
    return (
        typeof bottleneck.timestamp === 'string' &&
        typeof bottleneck.value === 'number' &&
        typeof bottleneck.duration === 'number' &&
        typeof bottleneck.impact === 'number'
    );
}

export function isResourceSaturation(value: unknown): value is Types.ResourceSaturation {
    if (!value || typeof value !== 'object') return false;
    const saturation = value as Types.ResourceSaturation;
    return (
        typeof saturation.current === 'number' &&
        typeof saturation.predicted === 'number' &&
        typeof saturation.timeToSaturation === 'number' &&
        Array.isArray(saturation.recommendations) &&
        saturation.recommendations.every(r => typeof r === 'string')
    );
}

export function isTrendAnalysis(value: unknown): value is Types.TrendAnalysis {
    if (!value || typeof value !== 'object') return false;
    const analysis = value as Types.TrendAnalysis;
    return (
        isExecutionTrends(analysis.executionTrends) &&
        isMemoryTrends(analysis.memoryTrends) &&
        isResourceTrends(analysis.resourceTrends)
    );
}

export function isExecutionTrends(value: unknown): value is Types.ExecutionTrends {
    if (!value || typeof value !== 'object') return false;
    const trends = value as Types.ExecutionTrends;
    return (
        isTrend(trends.trend) &&
        isSeasonality(trends.seasonality) &&
        Array.isArray(trends.anomalies) &&
        trends.anomalies.every(isAnomaly)
    );
}

export function isSeasonality(value: unknown): value is Types.Seasonality {
    if (!value || typeof value !== 'object') return false;
    const seasonality = value as Types.Seasonality;
    return (
        typeof seasonality.period === 'number' &&
        typeof seasonality.strength === 'number' &&
        Array.isArray(seasonality.pattern) &&
        seasonality.pattern.every(v => typeof v === 'number')
    );
}

export function isTrainingParameters(value: unknown): value is Types.TrainingParameters {
    if (!value || typeof value !== 'object') return false;
    const params = value as Types.TrainingParameters;
    return (
        typeof params.epochs === 'number' &&
        typeof params.batchSize === 'number' &&
        typeof params.validationSplit === 'number'
    );
}

// Model Type Guards
export function isModelParameters(value: unknown): value is Types.ModelParameters {
    if (!value || typeof value !== 'object') return false;
    const params = value as Types.ModelParameters;
    return (
        isLSTMParameters(params.lstm) &&
        isOptimizerParameters(params.optimizer) &&
        isTrainingParameters(params.training)
    );
}

export function isLSTMParameters(value: unknown): value is Types.LSTMParameters {
    if (!value || typeof value !== 'object') return false;
    const params = value as Types.LSTMParameters;
    return (
        typeof params.units === 'number' &&
        typeof params.layers === 'number' &&
        typeof params.dropout === 'number'
    );
}

export function isOptimizerParameters(value: unknown): value is Types.OptimizerParameters {
    if (!value || typeof value !== 'object') return false;
    const params = value as Types.OptimizerParameters;
    return (
        ['adam', 'rmsprop', 'sgd'].includes(params.type) &&
        typeof params.learningRate === 'number' &&
        (params.beta1 === undefined || typeof params.beta1 === 'number') &&
        (params.beta2 === undefined || typeof params.beta2 === 'number')
    );
}

// Performance Type Guards
export function isPerformanceResults(value: unknown): value is Types.PerformanceResults {
    if (!value || typeof value !== 'object') return false;
    const results = value as Types.PerformanceResults;
    return (
        Array.isArray(results.validationTimes) &&
        results.validationTimes.every(v => typeof v === 'number') &&
        Array.isArray(results.memoryUsage) &&
        results.memoryUsage.every(isMemoryUsage) &&
        Array.isArray(results.resourceUsage) &&
        results.resourceUsage.every(isResourceUsage)
    );
}

export function isMemoryUsage(value: unknown): value is Types.MemoryUsage {
    if (!value || typeof value !== 'object') return false;
    const usage = value as Types.MemoryUsage;
    return (
        typeof usage.heapUsed === 'number' &&
        typeof usage.heapTotal === 'number' &&
        typeof usage.timestamp === 'string'
    );
}

export function isResourceUsage(value: unknown): value is Types.ResourceUsage {
    if (!value || typeof value !== 'object') return false;
    const usage = value as Types.ResourceUsage;
    return (
        typeof usage.cpu === 'number' &&
        typeof usage.memory === 'number' &&
        typeof usage.timestamp === 'string'
    );
}

export function isDistributionStatistics(value: unknown): value is Types.DistributionStatistics {
    if (!value || typeof value !== 'object') return false;
    const stats = value as Types.DistributionStatistics;
    return (
        typeof stats.mean === 'number' &&
        typeof stats.median === 'number' &&
        typeof stats.mode === 'number' &&
        typeof stats.variance === 'number' &&
        typeof stats.standardDeviation === 'number' &&
        typeof stats.skewness === 'number' &&
        typeof stats.kurtosis === 'number' &&
        isQuantiles(stats.quantiles)
    );
}

export function isQuantiles(value: unknown): value is { q1: number; q2: number; q3: number } {
    if (!value || typeof value !== 'object') return false;
    const q = value as { q1: number; q2: number; q3: number };
    return (
        typeof q.q1 === 'number' &&
        typeof q.q2 === 'number' &&
        typeof q.q3 === 'number'
    );
}

export function isOutlierAnalysis(value: unknown): value is Types.OutlierAnalysis {
    if (!value || typeof value !== 'object') return false;
    const analysis = value as Types.OutlierAnalysis;
    return (
        ['iqr', 'zscore'].includes(analysis.method) &&
        Array.isArray(analysis.outliers) &&
        analysis.outliers.every(o => typeof o === 'number') &&
        typeof analysis.bounds.lower === 'number' &&
        typeof analysis.bounds.upper === 'number'
    );
}

// Statistical Type Guards
export function isDistributionAnalysis(value: unknown): value is Types.DistributionAnalysis {
    if (!value || typeof value !== 'object') return false;
    const analysis = value as Types.DistributionAnalysis;
    return (
        Array.isArray(analysis.normality) &&
        analysis.normality.every(isNormalityTest) &&
        isDistributionStatistics(analysis.statistics) &&
        isOutlierAnalysis(analysis.outliers)
    );
}

export function isNormalityTest(value: unknown): value is Types.NormalityTest {
    if (!value || typeof value !== 'object') return false;
    const test = value as Types.NormalityTest;
    return (
        ['shapiro-wilk', 'kolmogorov-smirnov'].includes(test.test) &&
        typeof test.statistic === 'number' &&
        typeof test.pValue === 'number' &&
        typeof test.isNormal === 'boolean'
    );
}

// Event Type Guards
export function isOptimizationEvent(value: unknown): value is Types.OptimizationEvent {
    if (!value || typeof value !== 'object') return false;
    const event = value as Types.OptimizationEvent;
    return (
        ['start', 'progress', 'complete'].includes(event.type) &&
        typeof event.timestamp === 'string'
    );
}

export function isValidationEvent(value: unknown): value is Types.ValidationEvent {
    if (!value || typeof value !== 'object') return false;
    const event = value as Types.ValidationEvent;
    return (
        ['error', 'warning', 'info'].includes(event.type) &&
        typeof event.message === 'string'
    );
}

// Utility Functions
export function assertType<T>(value: unknown, guard: (value: unknown) => value is T, message: string): T {
    if (!guard(value)) {
        throw new TypeError(message);
    }
    return value;
}

export function validateObject<T>(
    value: unknown,
    guard: (value: unknown) => value is T,
    path = ''
): { valid: boolean; errors: string[] } {
    const errors: string[] = [];
    try {
        assertType(value, guard, `Invalid type at ${path}`);
    } catch (error) {
        if (error instanceof TypeError) {
            errors.push(error.message);
        }
    }
    return { valid: errors.length === 0, errors };
}

export function validateArray<T>(
    values: unknown[],
    guard: (value: unknown) => value is T,
    path = ''
): { valid: boolean; errors: string[] } {
    const errors: string[] = [];
    values.forEach((value, index) => {
        try {
            assertType(value, guard, `Invalid type at ${path}[${index}]`);
        } catch (error) {
            if (error instanceof TypeError) {
                errors.push(error.message);
            }
        }
    });
    return { valid: errors.length === 0, errors };
}
