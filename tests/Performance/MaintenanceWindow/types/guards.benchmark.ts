import * as Guards from './guards';
import * as Types from './index';

/**
 * Benchmark comparisons for type guard optimizations
 */
class OptimizationBenchmark {
    private iterations: number;
    private warmupIterations: number;

    constructor(iterations: number = 10000, warmupIterations: number = 1000) {
        this.iterations = iterations;
        this.warmupIterations = warmupIterations;
    }

    /**
     * Run benchmark comparison
     */
    async compareBenchmarks(
        original: Function,
        optimized: Function,
        samples: any[]
    ): Promise<{
        originalStats: BenchmarkStats;
        optimizedStats: BenchmarkStats;
        improvement: BenchmarkImprovement;
    }> {
        // Warm up
        for (let i = 0; i < this.warmupIterations; i++) {
            original(samples[i % samples.length]);
            optimized(samples[i % samples.length]);
        }

        // Force GC before benchmarks
        if (global.gc) {
            global.gc();
        }

        const originalStats = await this.runBenchmark(original, samples);
        const optimizedStats = await this.runBenchmark(optimized, samples);

        return {
            originalStats,
            optimizedStats,
            improvement: this.calculateImprovement(originalStats, optimizedStats)
        };
    }

    /**
     * Run individual benchmark
     */
    private async runBenchmark(fn: Function, samples: any[]): Promise<BenchmarkStats> {
        const times: number[] = [];
        const memoryUsage: number[] = [];
        const gcPauses: number[] = [];

        const startHeap = process.memoryUsage().heapUsed;

        for (let i = 0; i < this.iterations; i++) {
            const sample = samples[i % samples.length];

            // Measure execution time
            const startTime = process.hrtime.bigint();
            fn(sample);
            const endTime = process.hrtime.bigint();
            times.push(Number(endTime - startTime) / 1e6); // Convert to ms

            // Measure memory usage
            const heap = process.memoryUsage().heapUsed;
            memoryUsage.push(heap);

            // Measure GC if needed
            if (i % 1000 === 0 && global.gc) {
                const gcStart = process.hrtime.bigint();
                global.gc();
                const gcEnd = process.hrtime.bigint();
                gcPauses.push(Number(gcEnd - gcStart) / 1e6);
            }
        }

        const endHeap = process.memoryUsage().heapUsed;

        return {
            executionTime: {
                mean: this.mean(times),
                median: this.median(times),
                p95: this.percentile(times, 95),
                min: Math.min(...times),
                max: Math.max(...times)
            },
            memory: {
                mean: this.mean(memoryUsage) / 1024 / 1024,
                peak: Math.max(...memoryUsage) / 1024 / 1024,
                growth: (endHeap - startHeap) / 1024 / 1024
            },
            gc: {
                totalPauses: gcPauses.length,
                meanPause: this.mean(gcPauses),
                maxPause: Math.max(...gcPauses)
            }
        };
    }

    /**
     * Calculate improvement percentages
     */
    private calculateImprovement(
        original: BenchmarkStats,
        optimized: BenchmarkStats
    ): BenchmarkImprovement {
        return {
            executionTime: {
                mean: this.percentChange(original.executionTime.mean, optimized.executionTime.mean),
                median: this.percentChange(original.executionTime.median, optimized.executionTime.median),
                p95: this.percentChange(original.executionTime.p95, optimized.executionTime.p95)
            },
            memory: {
                mean: this.percentChange(original.memory.mean, optimized.memory.mean),
                peak: this.percentChange(original.memory.peak, optimized.memory.peak),
                growth: this.percentChange(original.memory.growth, optimized.memory.growth)
            },
            gc: {
                pauses: this.percentChange(original.gc.totalPauses, optimized.gc.totalPauses),
                meanPause: this.percentChange(original.gc.meanPause, optimized.gc.meanPause)
            }
        };
    }

    /**
     * Generate benchmark report
     */
    generateReport(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): string {
        return `
=== Benchmark Comparison: ${name} ===

Execution Time (ms)
------------------
Original  | mean: ${original.executionTime.mean.toFixed(3)}, median: ${original.executionTime.median.toFixed(3)}, p95: ${original.executionTime.p95.toFixed(3)}
Optimized | mean: ${optimized.executionTime.mean.toFixed(3)}, median: ${optimized.executionTime.median.toFixed(3)}, p95: ${optimized.executionTime.p95.toFixed(3)}
Change    | mean: ${improvement.executionTime.mean.toFixed(1)}%, median: ${improvement.executionTime.median.toFixed(1)}%, p95: ${improvement.executionTime.p95.toFixed(1)}%

Memory Usage (MB)
---------------
Original  | mean: ${original.memory.mean.toFixed(2)}, peak: ${original.memory.peak.toFixed(2)}, growth: ${original.memory.growth.toFixed(2)}
Optimized | mean: ${optimized.memory.mean.toFixed(2)}, peak: ${optimized.memory.peak.toFixed(2)}, growth: ${optimized.memory.growth.toFixed(2)}
Change    | mean: ${improvement.memory.mean.toFixed(1)}%, peak: ${improvement.memory.peak.toFixed(1)}%, growth: ${improvement.memory.growth.toFixed(1)}%

Garbage Collection
----------------
Original  | pauses: ${original.gc.totalPauses}, mean pause: ${original.gc.meanPause.toFixed(2)}ms, max pause: ${original.gc.maxPause.toFixed(2)}ms
Optimized | pauses: ${optimized.gc.totalPauses}, mean pause: ${optimized.gc.meanPause.toFixed(2)}ms, max pause: ${optimized.gc.maxPause.toFixed(2)}ms
Change    | pauses: ${improvement.gc.pauses.toFixed(1)}%, mean pause: ${improvement.gc.meanPause.toFixed(1)}%
`;
    }

    /**
     * Utility functions
     */
    private mean(values: number[]): number {
        return values.reduce((a, b) => a + b, 0) / values.length;
    }

    private median(values: number[]): number {
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    }

    private percentile(values: number[], p: number): number {
        const sorted = [...values].sort((a, b) => a - b);
        const pos = (sorted.length - 1) * p / 100;
        const base = Math.floor(pos);
        const rest = pos - base;
        if (sorted[base + 1] !== undefined) {
            return sorted[base] + rest * (sorted[base + 1] - sorted[base]);
        }
        return sorted[base];
    }

    private percentChange(original: number, optimized: number): number {
        return ((optimized - original) / original) * 100;
    }
}

// Types
export interface BenchmarkStats {
    executionTime: {
        mean: number;
        median: number;
        p95: number;
        min: number;
        max: number;
    };
    memory: {
        mean: number;
        peak: number;
        growth: number;
    };
    gc: {
        totalPauses: number;
        meanPause: number;
        maxPause: number;
    };
}

export interface BenchmarkImprovement {
    executionTime: {
        mean: number;
        median: number;
        p95: number;
    };
    memory: {
        mean: number;
        peak: number;
        growth: number;
    };
    gc: {
        pauses: number;
        meanPause: number;
    };
}

/**
 * Benchmark tests
 */
describe('Type Guards Optimization Benchmarks', () => {
    let benchmark: OptimizationBenchmark;

    beforeEach(() => {
        benchmark = new OptimizationBenchmark();
        if (global.gc) {
            global.gc();
        }
    });

    describe('Object Validation Benchmarks', () => {
        it('should compare original vs optimized trend validation', async () => {
            // Original implementation
            const originalIsTrend = Guards.isTrend;

            // Optimized implementation with early returns
            const optimizedIsTrend = (value: unknown): value is Types.Trend => {
                if (!value || typeof value !== 'object') return false;
                const trend = value as Partial<Types.Trend>;
                if (typeof trend.slope !== 'number') return false;
                if (typeof trend.intercept !== 'number') return false;
                if (typeof trend.correlation !== 'number') return false;
                if (typeof trend.significance !== 'number') return false;
                return true;
            };

            const samples = Array(1000).fill(null).map(() => ({
                slope: Math.random(),
                intercept: Math.random(),
                correlation: Math.random(),
                significance: Math.random()
            }));

            const results = await benchmark.compareBenchmarks(
                originalIsTrend,
                optimizedIsTrend,
                samples
            );

            console.log(benchmark.generateReport(
                'Trend Validation',
                results.originalStats,
                results.optimizedStats,
                results.improvement
            ));

            // Verify optimizations are effective
            expect(results.improvement.executionTime.mean).toBeLessThan(0);
            expect(results.improvement.memory.mean).toBeLessThan(0);
        });
    });

    describe('Cached Validation Benchmarks', () => {
        it('should compare uncached vs cached validation', async () => {
            // Create validation cache
            class ValidationCache<T> {
                private cache = new Map<string, boolean>();
                constructor(private guard: (value: unknown) => value is T) {}

                validate(value: unknown): boolean {
                    const key = JSON.stringify(value);
                    if (this.cache.has(key)) return this.cache.get(key)!;
                    const result = this.guard(value);
                    this.cache.set(key, result);
                    return result;
                }
            }

            const cache = new ValidationCache<Types.Trend>(Guards.isTrend);
            const samples = Array(100).fill(null).map(() => ({
                slope: Math.random(),
                intercept: Math.random(),
                correlation: Math.random(),
                significance: Math.random()
            }));

            const results = await benchmark.compareBenchmarks(
                Guards.isTrend,
                (value: unknown) => cache.validate(value),
                samples
            );

            console.log(benchmark.generateReport(
                'Cached Validation',
                results.originalStats,
                results.optimizedStats,
                results.improvement
            ));

            // Verify caching is effective
            expect(results.improvement.executionTime.mean).toBeLessThan(-50); // At least 50% faster
        });
    });
});
