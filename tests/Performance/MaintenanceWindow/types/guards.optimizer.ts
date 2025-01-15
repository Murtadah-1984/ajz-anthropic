import * as Guards from './guards';
import * as Types from './index';

/**
 * Memory optimization analyzer and recommendations
 */
class MemoryOptimizer {
    private memoryThresholds = {
        primitiveValidation: 0.01, // MB
        objectValidation: 0.05,    // MB
        arrayValidation: 0.1,      // MB
        gcPause: 10,              // ms
        heapGrowth: 1             // MB per 1000 operations
    };

    private recommendations: Set<string> = new Set();
    private metrics: {
        allocations: number[];
        gcPauses: number[];
        heapGrowth: number[];
        validationTimes: number[];
    } = {
        allocations: [],
        gcPauses: [],
        heapGrowth: [],
        validationTimes: []
    };

    /**
     * Analyze type guard performance and generate recommendations
     */
    async analyzeTypeGuard(guard: Function, samples: any[]): Promise<void> {
        const startHeap = process.memoryUsage().heapUsed;
        const startTime = process.hrtime.bigint();

        for (const sample of samples) {
            const beforeHeap = process.memoryUsage().heapUsed;
            const beforeTime = process.hrtime.bigint();

            guard(sample);

            const afterTime = process.hrtime.bigint();
            const afterHeap = process.memoryUsage().heapUsed;

            this.metrics.allocations.push((afterHeap - beforeHeap) / 1024 / 1024);
            this.metrics.validationTimes.push(Number(afterTime - beforeTime) / 1e6);

            // Force GC occasionally to measure pauses
            if (samples.indexOf(sample) % 100 === 0 && global.gc) {
                const gcStart = process.hrtime.bigint();
                global.gc();
                const gcEnd = process.hrtime.bigint();
                this.metrics.gcPauses.push(Number(gcEnd - gcStart) / 1e6);
            }
        }

        const endHeap = process.memoryUsage().heapUsed;
        const endTime = process.hrtime.bigint();

        this.metrics.heapGrowth.push((endHeap - startHeap) / 1024 / 1024);
        this.analyzeMetrics();
    }

    /**
     * Analyze metrics and generate recommendations
     */
    private analyzeMetrics(): void {
        // Analyze allocation patterns
        const avgAllocation = this.average(this.metrics.allocations);
        if (avgAllocation > this.memoryThresholds.objectValidation) {
            this.recommendations.add(
                'High memory allocation detected. Consider implementing object pooling for frequently validated objects.'
            );
        }

        // Analyze GC impact
        const avgGcPause = this.average(this.metrics.gcPauses);
        if (avgGcPause > this.memoryThresholds.gcPause) {
            this.recommendations.add(
                'Long GC pauses detected. Consider batch processing validations to reduce GC pressure.'
            );
        }

        // Analyze heap growth
        const totalHeapGrowth = this.sum(this.metrics.heapGrowth);
        if (totalHeapGrowth > this.memoryThresholds.heapGrowth) {
            this.recommendations.add(
                'Significant heap growth observed. Consider implementing validation result caching for repeated validations.'
            );
        }

        // Analyze validation times
        const avgValidationTime = this.average(this.metrics.validationTimes);
        if (avgValidationTime > 1) { // More than 1ms
            this.recommendations.add(
                'Slow validation times detected. Consider simplifying validation logic or implementing early returns.'
            );
        }
    }

    /**
     * Generate optimization report
     */
    generateReport(): string {
        const report: string[] = [
            '=== Memory Optimization Report ===\n',
            'Performance Metrics:',
            `-----------------------------------------`,
            `Average Allocation: ${this.average(this.metrics.allocations).toFixed(3)} MB`,
            `Peak Allocation: ${Math.max(...this.metrics.allocations).toFixed(3)} MB`,
            `Average GC Pause: ${this.average(this.metrics.gcPauses).toFixed(2)} ms`,
            `Total Heap Growth: ${this.sum(this.metrics.heapGrowth).toFixed(2)} MB`,
            `Average Validation Time: ${this.average(this.metrics.validationTimes).toFixed(3)} ms\n`,
            'Optimization Recommendations:',
            `-----------------------------------------`,
            ...Array.from(this.recommendations),
            '\nImplementation Examples:',
            `-----------------------------------------`
        ];

        // Add implementation examples based on recommendations
        if (this.recommendations.size > 0) {
            report.push(this.generateImplementationExamples());
        }

        return report.join('\n');
    }

    /**
     * Generate implementation examples
     */
    private generateImplementationExamples(): string {
        const examples: string[] = [];

        // Object pooling example
        if (this.hasRecommendation('object pooling')) {
            examples.push(`
// Object Pool Implementation Example:
class ValidationPool<T> {
    private pool: T[] = [];
    private create: () => T;

    constructor(create: () => T, size: number = 1000) {
        this.create = create;
        this.pool = Array(size).fill(null).map(() => create());
    }

    acquire(): T {
        return this.pool.pop() ?? this.create();
    }

    release(obj: T): void {
        if (this.pool.length < 1000) {
            this.pool.push(obj);
        }
    }
}

// Usage:
const trendPool = new ValidationPool<Types.Trend>(() => ({
    slope: 0,
    intercept: 0,
    correlation: 0,
    significance: 0
}));`);
        }

        // Validation caching example
        if (this.hasRecommendation('caching')) {
            examples.push(`
// Validation Cache Implementation Example:
class ValidationCache<T> {
    private cache = new Map<string, boolean>();
    private guard: (value: unknown) => value is T;

    constructor(guard: (value: unknown) => value is T) {
        this.guard = guard;
    }

    validate(value: unknown): boolean {
        const key = JSON.stringify(value);
        if (this.cache.has(key)) {
            return this.cache.get(key)!;
        }
        const result = this.guard(value);
        this.cache.set(key, result);
        return result;
    }

    clear(): void {
        this.cache.clear();
    }
}

// Usage:
const trendCache = new ValidationCache<Types.Trend>(Guards.isTrend);`);
        }

        // Early return example
        if (this.hasRecommendation('early returns')) {
            examples.push(`
// Early Return Implementation Example:
function optimizedIsTrend(value: unknown): value is Types.Trend {
    if (!value || typeof value !== 'object') return false;

    // Type assertion after basic check
    const trend = value as Partial<Types.Trend>;

    // Early returns for required properties
    if (typeof trend.slope !== 'number') return false;
    if (typeof trend.intercept !== 'number') return false;
    if (typeof trend.correlation !== 'number') return false;
    if (typeof trend.significance !== 'number') return false;

    return true;
}`);
        }

        // Batch processing example
        if (this.hasRecommendation('batch processing')) {
            examples.push(`
// Batch Processing Implementation Example:
class BatchValidator<T> {
    private batchSize: number;
    private guard: (value: unknown) => value is T;
    private batch: unknown[] = [];
    private results: boolean[] = [];

    constructor(guard: (value: unknown) => value is T, batchSize: number = 1000) {
        this.guard = guard;
        this.batchSize = batchSize;
    }

    add(value: unknown): void {
        this.batch.push(value);
        if (this.batch.length >= this.batchSize) {
            this.processBatch();
        }
    }

    processBatch(): boolean[] {
        const results = this.batch.map(value => this.guard(value));
        this.results.push(...results);
        this.batch = [];
        return results;
    }

    getResults(): boolean[] {
        if (this.batch.length > 0) {
            this.processBatch();
        }
        return this.results;
    }
}

// Usage:
const batchValidator = new BatchValidator<Types.Trend>(Guards.isTrend);`);
        }

        return examples.join('\n');
    }

    /**
     * Check if recommendation exists
     */
    private hasRecommendation(keyword: string): boolean {
        return Array.from(this.recommendations).some(r => r.toLowerCase().includes(keyword));
    }

    /**
     * Utility functions
     */
    private average(values: number[]): number {
        return values.length > 0 ? this.sum(values) / values.length : 0;
    }

    private sum(values: number[]): number {
        return values.reduce((a, b) => a + b, 0);
    }
}

/**
 * Memory optimization tests
 */
describe('Type Guards Optimization', () => {
    let optimizer: MemoryOptimizer;

    beforeEach(() => {
        optimizer = new MemoryOptimizer();
        if (global.gc) {
            global.gc();
        }
    });

    describe('Primitive Type Guards Optimization', () => {
        it('should analyze and optimize primitive validations', async () => {
            const samples = ['1h', '6h', '12h', '24h', '7d', '30d'];
            await optimizer.analyzeTypeGuard(Guards.isTimeRange, samples);
            console.log('\nPrimitive Type Guard Optimization:');
            console.log(optimizer.generateReport());
        });
    });

    describe('Object Type Guards Optimization', () => {
        it('should analyze and optimize object validations', async () => {
            const samples = Array(1000).fill(null).map(() => ({
                slope: Math.random(),
                intercept: Math.random(),
                correlation: Math.random(),
                significance: Math.random()
            }));
            await optimizer.analyzeTypeGuard(Guards.isTrend, samples);
            console.log('\nObject Type Guard Optimization:');
            console.log(optimizer.generateReport());
        });
    });
});
