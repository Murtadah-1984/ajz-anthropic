/**
 * Performance Maintenance Window Tools Type Definitions
 */

// Common Types
export type TimeRange = '1h' | '6h' | '12h' | '24h' | '7d' | '30d';
export type MetricType = 'accuracy' | 'performance' | 'stability' | 'reliability';
export type Priority = 'low' | 'medium' | 'high';
export type ViewType = 'chart' | 'table' | 'grid' | 'heatmap';

// Base Types
export interface Trend {
    slope: number;
    intercept: number;
    correlation: number;
    significance: number;
}

export interface ConfidenceInterval {
    lower: number;
    upper: number;
    confidence: number;
}

export interface ExecutionTrends {
    trend: Trend;
    seasonality: Seasonality;
    anomalies: Anomaly[];
}

export interface Seasonality {
    period: number;
    strength: number;
    pattern: number[];
}

export interface Anomaly {
    index: number;
    value: number;
    score: number;
    type: 'spike' | 'dip' | 'shift';
}

// Analysis Types
export interface TrendAnalysis {
    executionTrends: ExecutionTrends;
    memoryTrends: MemoryTrends;
    resourceTrends: ResourceTrends;
}

export interface MemoryTrends {
    trend: Trend;
    growth: GrowthMetrics;
    leakProbability: number;
    fragmentation: FragmentationMetrics;
}

export interface ResourceTrends {
    cpu: ResourceMetrics;
    memory: ResourceMetrics;
    correlation: number;
    saturation: ResourceSaturation;
}

export interface ResourceSaturation {
    current: number;
    predicted: number;
    timeToSaturation: number;
    recommendations: string[];
}

export interface GrowthMetrics {
    rate: number;
    pattern: 'linear' | 'exponential' | 'logarithmic';
    stability: number;
}

export interface FragmentationMetrics {
    ratio: number;
    trend: Trend;
    impact: number;
}

export interface ResourceMetrics {
    utilization: number;
    trend: Trend;
    bottlenecks: Bottleneck[];
}

export interface Bottleneck {
    timestamp: string;
    value: number;
    duration: number;
    impact: number;
}

// Model Types
export interface ModelOptimizer {
    optimizeModel(type: string, name: string, timeRange?: TimeRange): Promise<OptimizationResults>;
    gridSearch(trainData: number[], validData: number[]): Promise<GridSearchResults>;
    evaluateModel(trainData: number[], validData: number[], params: ModelParameters): Promise<number>;
}

export interface OptimizationResults {
    optimal: ModelParameters;
    improvement: number;
    allResults: GridSearchResults[];
}

export interface GridSearchResults {
    params: ModelParameters;
    loss: number;
}

export interface ModelParameters {
    lstm: LSTMParameters;
    optimizer: OptimizerParameters;
    training: TrainingParameters;
}

export interface LSTMParameters {
    units: number;
    layers: number;
    dropout: number;
}

export interface OptimizerParameters {
    type: 'adam' | 'rmsprop' | 'sgd';
    learningRate: number;
    beta1?: number;
    beta2?: number;
}

export interface TrainingParameters {
    epochs: number;
    batchSize: number;
    validationSplit: number;
}

// Validation Types
export interface HyperparameterValidator {
    validate(): ValidationResults;
    validateStructure(params: ModelParameters, errors: string[], path?: string): void;
    validateRanges(params: ModelParameters, errors: string[], warnings: string[]): void;
}

export interface ValidationResults {
    isValid: boolean;
    errors: string[];
    warnings: string[];
}

// Performance Types
export interface PerfVisualizer {
    visualize(results: PerformanceResults): Promise<void>;
    generateExecutionTimeChart(results: PerformanceResults): Promise<void>;
}

export interface PerformanceResults {
    validationTimes: number[];
    memoryUsage: MemoryUsage[];
    resourceUsage: ResourceUsage[];
}

export interface MemoryUsage {
    heapUsed: number;
    heapTotal: number;
    timestamp: string;
}

export interface ResourceUsage {
    cpu: number;
    memory: number;
    timestamp: string;
}

// Evaluation Types
export interface TestData {
    features: number[][];
    labels: number[];
    metadata?: Record<string, any>;
}

export interface TrainingData {
    trainFeatures: number[][];
    trainLabels: number[];
    validationFeatures: number[][];
    validationLabels: number[];
}

export interface EvaluationOptions {
    metrics?: string[];
    threshold?: number;
    detailed?: boolean;
    crossValidation?: boolean;
}

export interface ValidationOptions {
    folds?: number;
    shuffle?: boolean;
    stratify?: boolean;
    metrics?: string[];
}

export interface ReliabilityFactors {
    dataQuality: number;
    modelStability: number;
    predictionConsistency: number;
    environmentalFactors: number;
}

// Statistical Types
export interface DistributionAnalysis {
    normality: NormalityTest[];
    statistics: DistributionStatistics;
    outliers: OutlierAnalysis;
}

export interface NormalityTest {
    test: 'shapiro-wilk' | 'kolmogorov-smirnov';
    statistic: number;
    pValue: number;
    isNormal: boolean;
}

export interface DistributionStatistics {
    mean: number;
    median: number;
    mode: number;
    variance: number;
    standardDeviation: number;
    skewness: number;
    kurtosis: number;
    quantiles: {
        q1: number;
        q2: number;
        q3: number;
    };
}

export interface OutlierAnalysis {
    method: 'iqr' | 'zscore';
    outliers: number[];
    bounds: {
        lower: number;
        upper: number;
    };
}

export interface EffectSizeAnalysis {
    cohensD: EffectSize;
    hedgesG: EffectSize;
    glasssDelta: EffectSize;
}

export interface EffectSize {
    value: number;
    confidence: ConfidenceInterval;
    interpretation: 'negligible' | 'small' | 'medium' | 'large';
}

export interface RegressionResults {
    model: RegressionModel;
    performance: RegressionPerformance;
    diagnostics: RegressionDiagnostics;
}

export interface RegressionModel {
    type: 'linear' | 'polynomial' | 'logistic';
    coefficients: number[];
    intercept: number;
    formula: string;
}

export interface RegressionPerformance {
    rSquared: number;
    adjustedRSquared: number;
    rmse: number;
    mae: number;
}

export interface RegressionDiagnostics {
    residuals: number[];
    leverage: number[];
    cookDistance: number[];
    vif: number[];
}

export interface AnovaResults {
    factors: string[];
    sumOfSquares: number[];
    degreesOfFreedom: number[];
    meanSquares: number[];
    fValues: number[];
    pValues: number[];
    etaSquared: number[];
}

// Event Types
export interface OptimizationEvent {
    type: 'start' | 'progress' | 'complete';
    data: any;
    timestamp: string;
}

export interface ValidationEvent {
    type: 'error' | 'warning' | 'info';
    message: string;
    details?: any;
}

export interface ProgressEvent {
    current: number;
    total: number;
    percentage: number;
    status: string;
}
