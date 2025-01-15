const { describe, expect, test, beforeEach } = require('@jest/globals');
const HyperparameterValidator = require('./hyperparameter-validator');
const fixtures = require('./fixtures/hyperparameter-fixtures');

describe('HyperparameterValidator', () => {
    let validator;

    beforeEach(() => {
        validator = new HyperparameterValidator();
    });

    describe('Structure Validation', () => {
        test('validates complete valid configuration', () => {
            validator.hyperparameters = fixtures.valid.basic;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        test('validates minimal valid configuration', () => {
            validator.hyperparameters = fixtures.valid.minimal;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        test('detects missing required parameters', () => {
            validator.hyperparameters = fixtures.invalid.missingRequired;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Missing required parameter: lstm');
        });

        test('validates comprehensive configuration', () => {
            validator.hyperparameters = fixtures.valid.comprehensive;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });
    });

    describe('Range Validation', () => {
        test('validates parameter ranges', () => {
            validator.hyperparameters = fixtures.invalid.invalidRanges;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('lstm.units value -1 outside range [1, 512]');
            expect(result.errors).toContain('lstm.units value 1000 outside range [1, 512]');
            expect(result.errors).toContain('lstm.dropout value -0.1 outside range [0, 0.9]');
            expect(result.errors).toContain('lstm.dropout value 1.5 outside range [0, 0.9]');
        });

        test('validates array types', () => {
            validator.hyperparameters = fixtures.invalid.invalidTypes;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('lstm.units must be an array');
            expect(result.errors).toContain('lstm.layers must be an array');
            expect(result.errors).toContain('lstm.dropout must be an array');
        });

        test('validates sorted arrays', () => {
            validator.hyperparameters = fixtures.invalid.unsortedArrays;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('lstm.units must be sorted');
            expect(result.errors).toContain('lstm.layers must be sorted');
            expect(result.errors).toContain('lstm.dropout must be sorted');
        });
    });

    describe('Resource Validation', () => {
        test('validates exceeded resources', () => {
            validator.hyperparameters = fixtures.resources.exceeded;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Estimated memory usage exceeds maximum');
            expect(result.errors).toContain('Estimated training time exceeds timeout');
        });

        test('validates optimal resources', () => {
            validator.hyperparameters = fixtures.resources.optimal;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });
    });

    describe('Warning Generation', () => {
        test('warns about complex architecture', () => {
            validator.hyperparameters = fixtures.warnings.complexArchitecture;
            const result = validator.validate();
            expect(result.warnings).toContain('Complex LSTM architecture may lead to instability');
        });

        test('warns about extreme learning rates', () => {
            validator.hyperparameters = fixtures.warnings.extremeLearningRates;
            const result = validator.validate();
            expect(result.warnings).toContain('Learning rate range may be too extreme');
        });

        test('warns about high epoch count', () => {
            validator.hyperparameters = fixtures.warnings.highEpochCount;
            const result = validator.validate();
            expect(result.warnings).toContain('High epoch count may lead to overfitting');
        });
    });

    describe('Optimization Suggestions', () => {
        test('suggests memory optimizations', () => {
            const result = validator.getOptimizationSuggestions(fixtures.optimization.memoryIntensive);
            const suggestion = result.find(s => s.type === 'memory');
            expect(suggestion).toBeDefined();
            expect(suggestion.priority).toBe('high');
            expect(suggestion.actions).toContain('Reduce LSTM units');
        });

        test('suggests performance optimizations', () => {
            const result = validator.getOptimizationSuggestions(fixtures.optimization.performanceIssues);
            const suggestion = result.find(s => s.type === 'performance');
            expect(suggestion).toBeDefined();
            expect(suggestion.priority).toBe('medium');
            expect(suggestion.actions).toContain('Increase batch size');
        });

        test('suggests architecture optimizations', () => {
            const result = validator.getOptimizationSuggestions(fixtures.optimization.complexArchitecture);
            const suggestion = result.find(s => s.type === 'architecture');
            expect(suggestion).toBeDefined();
            expect(suggestion.priority).toBe('low');
            expect(suggestion.actions).toContain('Reduce number of units');
        });
    });

    describe('Edge Cases', () => {
        test('handles empty arrays', () => {
            validator.hyperparameters = fixtures.edge.emptyArrays;
            const result = validator.validate();
            expect(result.isValid).toBe(false);
            expect(result.errors.length).toBeGreaterThan(0);
        });

        test('handles minimum values', () => {
            validator.hyperparameters = fixtures.edge.minimumValues;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        test('handles maximum values', () => {
            validator.hyperparameters = fixtures.edge.maximumValues;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });
    });

    describe('Combination Cases', () => {
        test('validates resource intensive configuration', () => {
            validator.hyperparameters = fixtures.combinations.resourceIntensive;
            const result = validator.validate();
            expect(result.warnings).toContain('Complex LSTM architecture may lead to instability');
        });

        test('validates lightweight configuration', () => {
            validator.hyperparameters = fixtures.combinations.lightWeight;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.warnings).toHaveLength(0);
        });
    });

    describe('Validation Cases', () => {
        test('validates cross validation configuration', () => {
            validator.hyperparameters = fixtures.validation.crossValidation;
            const result = validator.validate();
            expect(result.isValid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        test('warns about high early stopping patience', () => {
            validator.hyperparameters = fixtures.validation.earlyStoppingHigh;
            const result = validator.validate();
            expect(result.warnings).toContain('Early stopping patience may be too high');
        });
    });

    describe('Utility Functions', () => {
        test('correctly checks if array is sorted', () => {
            expect(validator.isSorted([1, 2, 3])).toBe(true);
            expect(validator.isSorted([1, 1, 2])).toBe(true);
            expect(validator.isSorted([3, 2, 1])).toBe(false);
        });

        test('correctly checks if array is monotonic', () => {
            expect(validator.isMonotonic([1, 2, 3])).toBe(true);
            expect(validator.isMonotonic([3, 2, 1])).toBe(true);
            expect(validator.isMonotonic([1, 3, 2])).toBe(false);
        });

        test('correctly estimates memory usage', () => {
            const params = fixtures.valid.minimal;
            const memory = validator.estimateMemoryUsage(params);
            expect(memory).toBe(32 * 1 * 32 * 4); // units * layers * batchSize * 4
        });
    });
});
