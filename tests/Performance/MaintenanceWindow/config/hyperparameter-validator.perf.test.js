const { describe, expect, test, beforeEach } = require('@jest/globals');
const HyperparameterValidator = require('./hyperparameter-validator');
const fixtures = require('./fixtures/hyperparameter-fixtures');

describe('HyperparameterValidator Performance', () => {
    let validator;
    let startTime;
    let endTime;

    beforeEach(() => {
        validator = new HyperparameterValidator();
        startTime = process.hrtime();
    });

    afterEach(() => {
        endTime = process.hrtime(startTime);
        const duration = endTime[0] * 1000 + endTime[1] / 1000000; // Convert to milliseconds
        console.log(`Test duration: ${duration.toFixed(2)}ms`);
    });

    /**
     * Measure execution time
     */
    const measureExecution = async (fn) => {
        const start = process.hrtime();
        const result = await fn();
        const end = process.hrtime(start);
        return {
            result,
            duration: end[0] * 1000 + end[1] / 1000000 // Convert to milliseconds
        };
    };

    /**
     * Generate large configuration
     */
    const generateLargeConfig = (size) => {
        const config = {
            lstm: {
                units: [],
                layers: [],
                dropout: []
            },
            optimizer: {
                type: ['adam'],
                learningRate: [],
                beta1: [0.9],
                beta2: [0.999]
            },
            training: {
                epochs: [],
                batchSize: []
            }
        };

        for (let i = 0; i < size; i++) {
            config.lstm.units.push(Math.floor(Math.random() * 512) + 1);
            config.lstm.layers.push(Math.floor(Math.random() * 5) + 1);
            config.lstm.dropout.push(Math.random() * 0.9);
            config.optimizer.learningRate.push(Math.random() * 0.01);
            config.training.epochs.push(Math.floor(Math.random() * 1000) + 1);
            config.training.batchSize.push(Math.pow(2, Math.floor(Math.random() * 6) + 4)); // 16 to 512
        }

        // Sort arrays to meet validation requirements
        config.lstm.units.sort((a, b) => a - b);
        config.lstm.layers.sort((a, b) => a - b);
        config.lstm.dropout.sort((a, b) => a - b);
        config.optimizer.learningRate.sort((a, b) => a - b);
        config.training.epochs.sort((a, b) => a - b);
        config.training.batchSize.sort((a, b) => a - b);

        return config;
    };

    describe('Validation Performance', () => {
        test('validates small configuration quickly', async () => {
            validator.hyperparameters = fixtures.valid.minimal;
            const { duration } = await measureExecution(() => validator.validate());
            expect(duration).toBeLessThan(10); // Should take less than 10ms
        });

        test('validates large configuration efficiently', async () => {
            validator.hyperparameters = generateLargeConfig(1000);
            const { duration } = await measureExecution(() => validator.validate());
            expect(duration).toBeLessThan(100); // Should take less than 100ms
        });

        test('handles multiple validations efficiently', async () => {
            const iterations = 100;
            const durations = [];

            for (let i = 0; i < iterations; i++) {
                validator.hyperparameters = fixtures.valid.comprehensive;
                const { duration } = await measureExecution(() => validator.validate());
                durations.push(duration);
            }

            const avgDuration = durations.reduce((a, b) => a + b) / iterations;
            const maxDuration = Math.max(...durations);

            expect(avgDuration).toBeLessThan(10); // Average should be less than 10ms
            expect(maxDuration).toBeLessThan(20); // Max should be less than 20ms
        });
    });

    describe('Memory Performance', () => {
        test('maintains stable memory usage with large configurations', async () => {
            const initialMemory = process.memoryUsage().heapUsed;
            const iterations = 100;
            const largeConfig = generateLargeConfig(1000);

            for (let i = 0; i < iterations; i++) {
                validator.hyperparameters = largeConfig;
                await validator.validate();
            }

            const finalMemory = process.memoryUsage().heapUsed;
            const memoryIncrease = (finalMemory - initialMemory) / 1024 / 1024; // MB

            expect(memoryIncrease).toBeLessThan(50); // Should not increase by more than 50MB
        });

        test('releases memory after validation', async () => {
            const getMemoryUsage = () => process.memoryUsage().heapUsed / 1024 / 1024;
            const initialMemory = getMemoryUsage();

            // Perform validation with large config
            validator.hyperparameters = generateLargeConfig(10000);
            await validator.validate();

            // Force garbage collection if available
            if (global.gc) {
                global.gc();
            }

            const finalMemory = getMemoryUsage();
            const memoryDiff = finalMemory - initialMemory;

            expect(memoryDiff).toBeLessThan(10); // Should not retain more than 10MB
        });
    });

    describe('Resource Estimation Performance', () => {
        test('estimates resources quickly for large configurations', async () => {
            const largeConfig = generateLargeConfig(1000);
            const { duration } = await measureExecution(() => {
                return validator.estimateMemoryUsage(largeConfig);
            });

            expect(duration).toBeLessThan(5); // Should take less than 5ms
        });

        test('performs multiple estimations efficiently', async () => {
            const iterations = 1000;
            const durations = [];

            for (let i = 0; i < iterations; i++) {
                const { duration } = await measureExecution(() => {
                    return validator.estimateMemoryUsage(fixtures.valid.comprehensive);
                });
                durations.push(duration);
            }

            const avgDuration = durations.reduce((a, b) => a + b) / iterations;
            expect(avgDuration).toBeLessThan(1); // Average should be less than 1ms
        });
    });

    describe('Optimization Suggestion Performance', () => {
        test('generates suggestions quickly for complex configurations', async () => {
            const largeConfig = generateLargeConfig(1000);
            const { duration } = await measureExecution(() => {
                return validator.getOptimizationSuggestions(largeConfig);
            });

            expect(duration).toBeLessThan(10); // Should take less than 10ms
        });

        test('handles repeated suggestion requests efficiently', async () => {
            const iterations = 100;
            const durations = [];

            for (let i = 0; i < iterations; i++) {
                const { duration } = await measureExecution(() => {
                    return validator.getOptimizationSuggestions(fixtures.optimization.memoryIntensive);
                });
                durations.push(duration);
            }

            const avgDuration = durations.reduce((a, b) => a + b) / iterations;
            expect(avgDuration).toBeLessThan(5); // Average should be less than 5ms
        });
    });

    describe('Concurrent Validation Performance', () => {
        test('handles concurrent validations efficiently', async () => {
            const concurrentValidations = 10;
            const validations = Array(concurrentValidations).fill(0).map(() => {
                return measureExecution(() => {
                    validator.hyperparameters = generateLargeConfig(100);
                    return validator.validate();
                });
            });

            const results = await Promise.all(validations);
            const maxDuration = Math.max(...results.map(r => r.duration));

            expect(maxDuration).toBeLessThan(50); // Should take less than 50ms per validation
        });

        test('maintains performance under load', async () => {
            const iterations = 10;
            const concurrentValidations = 5;
            const allDurations = [];

            for (let i = 0; i < iterations; i++) {
                const validations = Array(concurrentValidations).fill(0).map(() => {
                    return measureExecution(() => {
                        validator.hyperparameters = generateLargeConfig(100);
                        return validator.validate();
                    });
                });

                const results = await Promise.all(validations);
                allDurations.push(...results.map(r => r.duration));
            }

            const avgDuration = allDurations.reduce((a, b) => a + b) / allDurations.length;
            const maxDuration = Math.max(...allDurations);

            expect(avgDuration).toBeLessThan(20); // Average should be less than 20ms
            expect(maxDuration).toBeLessThan(50); // Max should be less than 50ms
        });
    });
});
