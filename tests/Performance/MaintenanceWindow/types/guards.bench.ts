import * as Guards from './guards';
import * as Types from './index';

/**
 * Performance benchmarks for type guards
 */
describe('Type Guards Performance', () => {
    // Test data
    const validTrend: Types.Trend = {
        slope: 1.5,
        intercept: 0.5,
        correlation: 0.95,
        significance: 0.01
    };

    const validMemoryTrends: Types.MemoryTrends = {
        trend: validTrend,
        growth: {
            rate: 1.2,
            pattern: 'linear',
            stability: 0.8
        },
        leakProbability: 0.05,
        fragmentation: {
            ratio: 0.3,
            trend: validTrend,
            impact: 0.4
        }
    };

    const validModelParams: Types.ModelParameters = {
        lstm: {
            units: 50,
            layers: 2,
            dropout: 0.2
        },
        optimizer: {
            type: 'adam',
            learningRate: 0.001,
            beta1: 0.9,
            beta2: 0.999
        },
        training: {
            epochs: 100,
            batchSize: 32,
            validationSplit: 0.2
        }
    };

    // Benchmark utilities
    const benchmark = (fn: () => void, iterations: number = 10000): number => {
        const start = process.hrtime.bigint();
        for (let i = 0; i < iterations; i++) {
            fn();
        }
        const end = process.hrtime.bigint();
        return Number(end - start) / 1e6 / iterations; // Average time in milliseconds
    };

    const assertPerformance = (actual: number, threshold: number) => {
        expect(actual).toBeLessThan(threshold);
    };

    // Benchmarks
    describe('Simple Type Guards', () => {
        it('should validate TimeRange efficiently', () => {
            const avgTime = benchmark(() => Guards.isTimeRange('1h'));
            assertPerformance(avgTime, 0.01); // Should take less than 0.01ms
            console.log(`TimeRange validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should validate MetricType efficiently', () => {
            const avgTime = benchmark(() => Guards.isMetricType('accuracy'));
            assertPerformance(avgTime, 0.01);
            console.log(`MetricType validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should validate Priority efficiently', () => {
            const avgTime = benchmark(() => Guards.isPriority('high'));
            assertPerformance(avgTime, 0.01);
            console.log(`Priority validation: ${avgTime.toFixed(4)}ms`);
        });
    });

    describe('Object Type Guards', () => {
        it('should validate Trend efficiently', () => {
            const avgTime = benchmark(() => Guards.isTrend(validTrend));
            assertPerformance(avgTime, 0.05);
            console.log(`Trend validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should validate MemoryTrends efficiently', () => {
            const avgTime = benchmark(() => Guards.isMemoryTrends(validMemoryTrends));
            assertPerformance(avgTime, 0.1);
            console.log(`MemoryTrends validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should validate ModelParameters efficiently', () => {
            const avgTime = benchmark(() => Guards.isModelParameters(validModelParams));
            assertPerformance(avgTime, 0.1);
            console.log(`ModelParameters validation: ${avgTime.toFixed(4)}ms`);
        });
    });

    describe('Validation Utilities', () => {
        it('should validate objects efficiently', () => {
            const avgTime = benchmark(() => Guards.validateObject(validTrend, Guards.isTrend));
            assertPerformance(avgTime, 0.1);
            console.log(`Object validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should validate arrays efficiently', () => {
            const trends = Array(100).fill(validTrend);
            const avgTime = benchmark(() => Guards.validateArray(trends, Guards.isTrend), 100);
            assertPerformance(avgTime, 1.0);
            console.log(`Array validation: ${avgTime.toFixed(4)}ms`);
        });
    });

    describe('Stress Tests', () => {
        it('should handle large objects efficiently', () => {
            const largeObject = {
                trends: Array(1000).fill(validMemoryTrends),
                parameters: Array(1000).fill(validModelParams)
            };
            const avgTime = benchmark(() => {
                Guards.validateObject(largeObject.trends[0], Guards.isMemoryTrends);
                Guards.validateObject(largeObject.parameters[0], Guards.isModelParameters);
            }, 100);
            assertPerformance(avgTime, 2.0);
            console.log(`Large object validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should handle concurrent validations efficiently', () => {
            const avgTime = benchmark(() => {
                Promise.all([
                    Guards.validateObject(validTrend, Guards.isTrend),
                    Guards.validateObject(validMemoryTrends, Guards.isMemoryTrends),
                    Guards.validateObject(validModelParams, Guards.isModelParameters)
                ]);
            }, 100);
            assertPerformance(avgTime, 1.0);
            console.log(`Concurrent validations: ${avgTime.toFixed(4)}ms`);
        });
    });

    describe('Edge Cases', () => {
        it('should handle null/undefined efficiently', () => {
            const avgTime = benchmark(() => {
                Guards.isTrend(null);
                Guards.isTrend(undefined);
            });
            assertPerformance(avgTime, 0.01);
            console.log(`Null/undefined validation: ${avgTime.toFixed(4)}ms`);
        });

        it('should handle invalid types efficiently', () => {
            const avgTime = benchmark(() => {
                Guards.isTrend('not an object');
                Guards.isTrend(123);
                Guards.isTrend([]);
            });
            assertPerformance(avgTime, 0.01);
            console.log(`Invalid type validation: ${avgTime.toFixed(4)}ms`);
        });
    });
});
