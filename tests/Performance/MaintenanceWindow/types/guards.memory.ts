import * as Guards from './guards';
import * as Types from './index';

/**
 * Memory usage tracking for type guards
 */
describe('Type Guards Memory Usage', () => {
    // Memory tracking utilities
    const getMemoryUsage = () => {
        const used = process.memoryUsage();
        return {
            heapUsed: used.heapUsed / 1024 / 1024, // MB
            heapTotal: used.heapTotal / 1024 / 1024, // MB
            external: used.external / 1024 / 1024, // MB
            rss: used.rss / 1024 / 1024 // MB
        };
    };

    const trackMemory = async (fn: () => void | Promise<void>, iterations: number = 1000): Promise<{
        peakHeapUsed: number;
        averageHeapUsed: number;
        heapGrowth: number;
        gcCalls: number;
    }> => {
        // Force garbage collection if available
        if (global.gc) {
            global.gc();
        }

        const measurements: number[] = [];
        const initialMemory = getMemoryUsage().heapUsed;
        let peakHeapUsed = initialMemory;
        let gcCalls = 0;

        for (let i = 0; i < iterations; i++) {
            const beforeMemory = getMemoryUsage().heapUsed;
            await fn();
            const afterMemory = getMemoryUsage().heapUsed;

            measurements.push(afterMemory);
            peakHeapUsed = Math.max(peakHeapUsed, afterMemory);

            // Check if GC likely ran
            if (afterMemory < beforeMemory) {
                gcCalls++;
            }

            // Allow event loop to process
            await new Promise(resolve => setTimeout(resolve, 0));
        }

        const finalMemory = getMemoryUsage().heapUsed;
        const averageHeapUsed = measurements.reduce((a, b) => a + b, 0) / measurements.length;
        const heapGrowth = finalMemory - initialMemory;

        return {
            peakHeapUsed,
            averageHeapUsed,
            heapGrowth,
            gcCalls
        };
    };

    const assertMemoryUsage = (usage: number, threshold: number) => {
        expect(usage).toBeLessThan(threshold);
    };

    // Test data
    const generateLargeDataset = (size: number): any[] => {
        return Array(size).fill(null).map(() => ({
            trend: {
                slope: Math.random(),
                intercept: Math.random(),
                correlation: Math.random(),
                significance: Math.random()
            },
            metrics: {
                accuracy: Math.random(),
                precision: Math.random(),
                recall: Math.random(),
                f1Score: Math.random()
            },
            parameters: {
                lstm: {
                    units: Math.floor(Math.random() * 100),
                    layers: Math.floor(Math.random() * 5),
                    dropout: Math.random()
                },
                optimizer: {
                    type: 'adam',
                    learningRate: Math.random() * 0.01
                }
            }
        }));
    };

    // Memory tests
    describe('Simple Type Guards Memory Usage', () => {
        it('should have minimal memory impact for primitive validations', async () => {
            const result = await trackMemory(() => {
                Guards.isTimeRange('1h');
                Guards.isMetricType('accuracy');
                Guards.isPriority('high');
            }, 10000);

            assertMemoryUsage(result.heapGrowth, 1); // Less than 1MB growth
            console.log('Simple validations memory usage:', {
                peakHeapUsed: `${result.peakHeapUsed.toFixed(2)}MB`,
                averageHeapUsed: `${result.averageHeapUsed.toFixed(2)}MB`,
                heapGrowth: `${result.heapGrowth.toFixed(2)}MB`,
                gcCalls: result.gcCalls
            });
        });
    });

    describe('Object Type Guards Memory Usage', () => {
        it('should efficiently handle object validations', async () => {
            const dataset = generateLargeDataset(1000);
            const result = await trackMemory(() => {
                dataset.forEach(data => {
                    Guards.isTrend(data.trend);
                    Guards.validateObject(data.parameters, Guards.isModelParameters);
                });
            }, 100);

            assertMemoryUsage(result.heapGrowth, 10); // Less than 10MB growth
            console.log('Object validations memory usage:', {
                peakHeapUsed: `${result.peakHeapUsed.toFixed(2)}MB`,
                averageHeapUsed: `${result.averageHeapUsed.toFixed(2)}MB`,
                heapGrowth: `${result.heapGrowth.toFixed(2)}MB`,
                gcCalls: result.gcCalls
            });
        });
    });

    describe('Array Validation Memory Usage', () => {
        it('should handle large arrays efficiently', async () => {
            const largeArray = generateLargeDataset(10000);
            const result = await trackMemory(() => {
                Guards.validateArray(largeArray.map(d => d.trend), Guards.isTrend);
            }, 10);

            assertMemoryUsage(result.heapGrowth, 50); // Less than 50MB growth
            console.log('Large array validation memory usage:', {
                peakHeapUsed: `${result.peakHeapUsed.toFixed(2)}MB`,
                averageHeapUsed: `${result.averageHeapUsed.toFixed(2)}MB`,
                heapGrowth: `${result.heapGrowth.toFixed(2)}MB`,
                gcCalls: result.gcCalls
            });
        });
    });

    describe('Memory Leak Detection', () => {
        it('should not leak memory during repeated validations', async () => {
            const measurements: number[] = [];
            const iterations = 100;

            for (let i = 0; i < iterations; i++) {
                const dataset = generateLargeDataset(100);
                const beforeMemory = getMemoryUsage().heapUsed;

                await trackMemory(() => {
                    dataset.forEach(data => {
                        Guards.validateObject(data, Guards.isTrend);
                        Guards.validateObject(data.parameters, Guards.isModelParameters);
                    });
                }, 10);

                const afterMemory = getMemoryUsage().heapUsed;
                measurements.push(afterMemory - beforeMemory);

                // Allow GC to run
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            // Calculate memory growth trend
            const memoryGrowthTrend = measurements.slice(-10).reduce((a, b) => a + b, 0) / 10;
            assertMemoryUsage(memoryGrowthTrend, 1); // Average growth should be less than 1MB

            console.log('Memory leak test results:', {
                averageGrowth: `${memoryGrowthTrend.toFixed(2)}MB`,
                measurements: measurements.map(m => m.toFixed(2))
            });
        });
    });

    describe('Concurrent Validation Memory Usage', () => {
        it('should handle concurrent validations efficiently', async () => {
            const dataset = generateLargeDataset(1000);
            const result = await trackMemory(async () => {
                await Promise.all(
                    dataset.map(data =>
                        Promise.all([
                            Guards.validateObject(data.trend, Guards.isTrend),
                            Guards.validateObject(data.parameters, Guards.isModelParameters)
                        ])
                    )
                );
            }, 10);

            assertMemoryUsage(result.heapGrowth, 100); // Less than 100MB growth
            console.log('Concurrent validations memory usage:', {
                peakHeapUsed: `${result.peakHeapUsed.toFixed(2)}MB`,
                averageHeapUsed: `${result.averageHeapUsed.toFixed(2)}MB`,
                heapGrowth: `${result.heapGrowth.toFixed(2)}MB`,
                gcCalls: result.gcCalls
            });
        });
    });
});
