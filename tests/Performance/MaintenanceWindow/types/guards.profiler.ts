import * as Guards from './guards';
import * as Types from './index';

/**
 * Memory profiling utilities for type guards
 */
class MemoryProfiler {
    private snapshots: Map<string, number> = new Map();
    private samples: Map<string, number[]> = new Map();
    private startTime: number = Date.now();

    /**
     * Take a memory snapshot
     */
    takeSnapshot(label: string): void {
        const memory = process.memoryUsage();
        this.snapshots.set(label, memory.heapUsed);
        if (!this.samples.has(label)) {
            this.samples.set(label, []);
        }
        this.samples.get(label)!.push(memory.heapUsed);
    }

    /**
     * Calculate memory delta between snapshots
     */
    getDelta(startLabel: string, endLabel: string): number {
        const start = this.snapshots.get(startLabel);
        const end = this.snapshots.get(endLabel);
        if (!start || !end) {
            throw new Error(`Missing snapshot for ${!start ? startLabel : endLabel}`);
        }
        return (end - start) / 1024 / 1024; // MB
    }

    /**
     * Get memory usage statistics for a label
     */
    getStats(label: string): {
        min: number;
        max: number;
        avg: number;
        std: number;
        samples: number;
    } {
        const samples = this.samples.get(label);
        if (!samples || samples.length === 0) {
            throw new Error(`No samples for ${label}`);
        }

        const values = samples.map(s => s / 1024 / 1024); // Convert to MB
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        const std = Math.sqrt(
            values.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / values.length
        );

        return {
            min: Math.min(...values),
            max: Math.max(...values),
            avg,
            std,
            samples: values.length
        };
    }

    /**
     * Generate memory profile report
     */
    generateReport(): string {
        const report: string[] = [
            '=== Memory Profile Report ===',
            `Duration: ${((Date.now() - this.startTime) / 1000).toFixed(2)}s\n`
        ];

        // Add snapshot deltas
        report.push('Memory Deltas (MB):');
        const labels = Array.from(this.snapshots.keys());
        for (let i = 0; i < labels.length - 1; i++) {
            const delta = this.getDelta(labels[i], labels[i + 1]);
            report.push(`${labels[i]} → ${labels[i + 1]}: ${delta.toFixed(2)}`);
        }
        report.push('');

        // Add statistics
        report.push('Memory Statistics (MB):');
        for (const label of this.samples.keys()) {
            const stats = this.getStats(label);
            report.push(`${label}:`);
            report.push(`  Min: ${stats.min.toFixed(2)}`);
            report.push(`  Max: ${stats.max.toFixed(2)}`);
            report.push(`  Avg: ${stats.avg.toFixed(2)}`);
            report.push(`  Std: ${stats.std.toFixed(2)}`);
            report.push(`  Samples: ${stats.samples}`);
        }

        return report.join('\n');
    }
}

/**
 * Memory profiling tests
 */
describe('Type Guards Memory Profile', () => {
    let profiler: MemoryProfiler;

    beforeEach(() => {
        profiler = new MemoryProfiler();
        if (global.gc) {
            global.gc();
        }
    });

    describe('Simple Type Guards Profile', () => {
        it('should profile primitive validations', async () => {
            profiler.takeSnapshot('start');

            // Warm-up phase
            for (let i = 0; i < 1000; i++) {
                Guards.isTimeRange('1h');
                Guards.isMetricType('accuracy');
                Guards.isPriority('high');
            }

            profiler.takeSnapshot('after-warmup');

            // Test phase
            for (let i = 0; i < 10000; i++) {
                Guards.isTimeRange('1h');
                Guards.isMetricType('accuracy');
                Guards.isPriority('high');

                if (i % 1000 === 0) {
                    profiler.takeSnapshot(`iteration-${i}`);
                }
            }

            profiler.takeSnapshot('end');
            console.log('\nPrimitive Validations Profile:');
            console.log(profiler.generateReport());
        });
    });

    describe('Object Type Guards Profile', () => {
        it('should profile nested object validations', async () => {
            const data = {
                trend: {
                    slope: 1.5,
                    intercept: 0.5,
                    correlation: 0.95,
                    significance: 0.01
                },
                parameters: {
                    lstm: {
                        units: 50,
                        layers: 2,
                        dropout: 0.2
                    },
                    optimizer: {
                        type: 'adam',
                        learningRate: 0.001
                    }
                }
            };

            profiler.takeSnapshot('start');

            // Warm-up phase
            for (let i = 0; i < 1000; i++) {
                Guards.validateObject(data.trend, Guards.isTrend);
                Guards.validateObject(data.parameters, Guards.isModelParameters);
            }

            profiler.takeSnapshot('after-warmup');

            // Test phase with increasing object complexity
            for (let i = 0; i < 5; i++) {
                const complexData = Array(Math.pow(10, i))
                    .fill(null)
                    .map(() => ({ ...data }));

                profiler.takeSnapshot(`before-complexity-${i}`);

                complexData.forEach(item => {
                    Guards.validateObject(item.trend, Guards.isTrend);
                    Guards.validateObject(item.parameters, Guards.isModelParameters);
                });

                profiler.takeSnapshot(`after-complexity-${i}`);

                // Allow GC to run
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            profiler.takeSnapshot('end');
            console.log('\nNested Object Validations Profile:');
            console.log(profiler.generateReport());
        });
    });

    describe('Memory Allocation Patterns', () => {
        it('should analyze memory allocation patterns', async () => {
            const patterns: { [key: string]: number } = {};
            const iterations = 1000;

            profiler.takeSnapshot('start');

            for (let i = 0; i < iterations; i++) {
                const beforeMemory = process.memoryUsage().heapUsed;

                // Perform validations
                Guards.validateObject({
                    trend: {
                        slope: Math.random(),
                        intercept: Math.random(),
                        correlation: Math.random(),
                        significance: Math.random()
                    }
                }, Guards.isTrend);

                const afterMemory = process.memoryUsage().heapUsed;
                const allocation = afterMemory - beforeMemory;

                // Track allocation size frequency
                const bucket = Math.floor(allocation / 1024); // Group by KB
                patterns[bucket] = (patterns[bucket] || 0) + 1;

                if (i % 100 === 0) {
                    profiler.takeSnapshot(`iteration-${i}`);
                }
            }

            profiler.takeSnapshot('end');

            // Analyze allocation patterns
            const sortedPatterns = Object.entries(patterns)
                .sort(([a], [b]) => Number(a) - Number(b))
                .map(([size, count]) => `${size}KB: ${count} times`);

            console.log('\nMemory Allocation Patterns:');
            console.log(profiler.generateReport());
            console.log('\nAllocation Size Distribution:');
            console.log(sortedPatterns.join('\n'));
        });
    });

    describe('Garbage Collection Impact', () => {
        it('should analyze GC impact on validations', async () => {
            const gcTimes: number[] = [];
            let lastGc = Date.now();

            profiler.takeSnapshot('start');

            for (let i = 0; i < 1000; i++) {
                const data = Array(100).fill(null).map(() => ({
                    trend: {
                        slope: Math.random(),
                        intercept: Math.random(),
                        correlation: Math.random(),
                        significance: Math.random()
                    }
                }));

                profiler.takeSnapshot(`before-validation-${i}`);

                // Perform validations
                data.forEach(item => {
                    Guards.validateObject(item.trend, Guards.isTrend);
                });

                profiler.takeSnapshot(`after-validation-${i}`);

                // Force GC every 100 iterations
                if (i % 100 === 0 && global.gc) {
                    const before = Date.now();
                    global.gc();
                    const duration = Date.now() - before;
                    gcTimes.push(duration);

                    profiler.takeSnapshot(`after-gc-${i}`);

                    // Calculate time since last GC
                    const timeSinceLastGc = Date.now() - lastGc;
                    lastGc = Date.now();

                    console.log(`GC at iteration ${i}:`, {
                        duration: `${duration}ms`,
                        timeSinceLastGc: `${timeSinceLastGc}ms`
                    });
                }
            }

            profiler.takeSnapshot('end');

            // Analyze GC impact
            const avgGcTime = gcTimes.reduce((a, b) => a + b, 0) / gcTimes.length;
            const maxGcTime = Math.max(...gcTimes);
            const minGcTime = Math.min(...gcTimes);

            console.log('\nGarbage Collection Impact:');
            console.log(profiler.generateReport());
            console.log('\nGC Statistics:');
            console.log({
                averageGcTime: `${avgGcTime.toFixed(2)}ms`,
                maxGcTime: `${maxGcTime}ms`,
                minGcTime: `${minGcTime}ms`,
                totalGcRuns: gcTimes.length
            });
        });
    });
});
