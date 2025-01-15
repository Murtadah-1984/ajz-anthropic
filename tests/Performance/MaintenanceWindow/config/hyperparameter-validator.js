const hyperparameters = require('./hyperparameters');

/**
 * Hyperparameter validation and optimization utilities
 */
class HyperparameterValidator {
    constructor() {
        this.hyperparameters = hyperparameters;
        this.validationRules = this.createValidationRules();
    }

    /**
     * Create validation rules
     */
    createValidationRules() {
        return {
            lstm: {
                units: {
                    type: 'number[]',
                    min: 1,
                    max: 512,
                    sorted: true,
                    validate: (units) => units.every(u => Number.isInteger(u))
                },
                layers: {
                    type: 'number[]',
                    min: 1,
                    max: 5,
                    sorted: true,
                    validate: (layers) => layers.every(l => Number.isInteger(l))
                },
                dropout: {
                    type: 'number[]',
                    min: 0,
                    max: 0.9,
                    sorted: true
                }
            },
            optimizer: {
                learningRate: {
                    type: 'number[]',
                    min: 1e-6,
                    max: 1,
                    sorted: true
                },
                beta1: {
                    type: 'number[]',
                    min: 0,
                    max: 1
                },
                beta2: {
                    type: 'number[]',
                    min: 0,
                    max: 1
                }
            },
            training: {
                epochs: {
                    type: 'number[]',
                    min: 1,
                    max: 1000,
                    sorted: true,
                    validate: (epochs) => epochs.every(e => Number.isInteger(e))
                },
                batchSize: {
                    type: 'number[]',
                    min: 1,
                    max: 512,
                    sorted: true,
                    validate: (sizes) => sizes.every(s => Number.isInteger(s) && (s & (s - 1)) === 0) // Power of 2
                }
            }
        };
    }

    /**
     * Validate hyperparameters
     */
    validate() {
        const errors = [];
        const warnings = [];

        // Validate structure
        this.validateStructure(this.hyperparameters, errors);

        // Validate parameter ranges
        this.validateRanges(this.hyperparameters, errors, warnings);

        // Validate parameter combinations
        this.validateCombinations(this.hyperparameters, errors, warnings);

        // Validate resource constraints
        this.validateResources(this.hyperparameters, errors, warnings);

        return {
            isValid: errors.length === 0,
            errors,
            warnings
        };
    }

    /**
     * Validate hyperparameter structure
     */
    validateStructure(params, errors, path = '') {
        for (const [key, rule] of Object.entries(this.validationRules)) {
            if (!params[key]) {
                errors.push(`Missing required parameter: ${path}${key}`);
                continue;
            }

            if (typeof rule === 'object' && !Array.isArray(rule)) {
                this.validateStructure(params[key], errors, `${path}${key}.`);
            } else {
                this.validateParameter(key, params[key], rule, errors);
            }
        }
    }

    /**
     * Validate parameter ranges
     */
    validateRanges(params, errors, warnings) {
        // LSTM parameters
        if (params.lstm) {
            this.validateRange('lstm.units', params.lstm.units, 1, 512, errors);
            this.validateRange('lstm.dropout', params.lstm.dropout, 0, 0.9, errors);

            // Warning for potentially inefficient configurations
            if (Math.max(...params.lstm.units) > 256) {
                warnings.push('Large LSTM units may lead to slow training');
            }
        }

        // Optimizer parameters
        if (params.optimizer) {
            this.validateRange('optimizer.learningRate', params.optimizer.learningRate, 1e-6, 1, errors);
            this.validateRange('optimizer.beta1', params.optimizer.beta1, 0, 1, errors);
            this.validateRange('optimizer.beta2', params.optimizer.beta2, 0, 1, errors);

            // Warning for extreme learning rates
            const lrs = params.optimizer.learningRate;
            if (Math.min(...lrs) < 1e-5 || Math.max(...lrs) > 0.1) {
                warnings.push('Learning rate range may be too extreme');
            }
        }

        // Training parameters
        if (params.training) {
            this.validateRange('training.epochs', params.training.epochs, 1, 1000, errors);
            this.validateRange('training.batchSize', params.training.batchSize, 1, 512, errors);

            // Warning for very long training
            if (Math.max(...params.training.epochs) > 500) {
                warnings.push('High epoch count may lead to overfitting');
            }
        }
    }

    /**
     * Validate parameter combinations
     */
    validateCombinations(params, errors, warnings) {
        // Validate LSTM layer combinations
        if (params.lstm) {
            const maxUnits = Math.max(...params.lstm.units);
            const maxLayers = Math.max(...params.lstm.layers);

            // Check for potentially unstable configurations
            if (maxUnits * maxLayers > 1024) {
                warnings.push('Complex LSTM architecture may lead to instability');
            }

            // Validate dropout progression
            const dropouts = params.lstm.dropout;
            if (!this.isMonotonic(dropouts)) {
                warnings.push('Consider monotonically increasing dropout rates');
            }
        }

        // Validate optimizer combinations
        if (params.optimizer) {
            const { type, learningRate } = params.optimizer;

            // Check optimizer-specific configurations
            if (type.includes('adam')) {
                if (!params.optimizer.beta1 || !params.optimizer.beta2) {
                    errors.push('Adam optimizer requires beta1 and beta2 parameters');
                }
            }

            // Validate learning rate schedule
            if (params.validation?.learningRateSchedule) {
                const schedule = params.validation.learningRateSchedule;
                if (Math.min(...learningRate) < schedule.minLR) {
                    errors.push('Learning rate range conflicts with schedule minimum');
                }
            }
        }

        // Validate training combinations
        if (params.training) {
            const maxBatchSize = Math.max(...params.training.batchSize);
            const minEpochs = Math.min(...params.training.epochs);

            // Check for insufficient training
            if (maxBatchSize > 256 && minEpochs < 20) {
                warnings.push('Large batch size may require more epochs');
            }

            // Validate early stopping configuration
            if (params.validation?.earlyStoping) {
                const patience = params.validation.earlyStoping.patience;
                if (patience > minEpochs / 4) {
                    warnings.push('Early stopping patience may be too high');
                }
            }
        }
    }

    /**
     * Validate resource constraints
     */
    validateResources(params, errors, warnings) {
        const resources = params.resources;
        if (!resources) return;

        // Validate memory constraints
        const estimatedMemory = this.estimateMemoryUsage(params);
        if (estimatedMemory > resources.maxMemory) {
            errors.push('Estimated memory usage exceeds maximum');
        } else if (estimatedMemory > resources.maxMemory * 0.8) {
            warnings.push('Estimated memory usage is close to maximum');
        }

        // Validate GPU memory constraints
        if (resources.maxGPUMemory) {
            const estimatedGPUMemory = this.estimateGPUMemoryUsage(params);
            if (estimatedGPUMemory > resources.maxGPUMemory) {
                errors.push('Estimated GPU memory usage exceeds maximum');
            }
        }

        // Validate batch size constraints
        const maxBatchSize = Math.max(...params.training.batchSize);
        if (maxBatchSize > resources.batchSizeLimit) {
            errors.push('Batch size exceeds resource limit');
        }

        // Validate training time constraints
        const estimatedTime = this.estimateTrainingTime(params);
        if (estimatedTime > resources.timeout) {
            errors.push('Estimated training time exceeds timeout');
        }
    }

    /**
     * Validate parameter range
     */
    validateRange(name, values, min, max, errors) {
        if (!Array.isArray(values)) {
            errors.push(`${name} must be an array`);
            return;
        }

        for (const value of values) {
            if (value < min || value > max) {
                errors.push(`${name} value ${value} outside range [${min}, ${max}]`);
            }
        }
    }

    /**
     * Validate parameter
     */
    validateParameter(key, value, rule, errors) {
        if (rule.type === 'number[]') {
            if (!Array.isArray(value)) {
                errors.push(`${key} must be an array`);
                return;
            }

            if (rule.validate && !value.every(rule.validate)) {
                errors.push(`${key} contains invalid values`);
            }

            if (rule.sorted && !this.isSorted(value)) {
                errors.push(`${key} must be sorted`);
            }
        }
    }

    /**
     * Check if array is sorted
     */
    isSorted(arr) {
        return arr.every((v, i) => i === 0 || v >= arr[i - 1]);
    }

    /**
     * Check if array is monotonic
     */
    isMonotonic(arr) {
        return this.isSorted(arr) || arr.every((v, i) => i === 0 || v <= arr[i - 1]);
    }

    /**
     * Estimate memory usage
     */
    estimateMemoryUsage(params) {
        const maxUnits = Math.max(...params.lstm.units);
        const maxLayers = Math.max(...params.lstm.layers);
        const maxBatchSize = Math.max(...params.training.batchSize);

        // Rough estimation based on model architecture
        return maxUnits * maxLayers * maxBatchSize * 4; // 4 bytes per float
    }

    /**
     * Estimate GPU memory usage
     */
    estimateGPUMemoryUsage(params) {
        // Similar to memory usage but with additional overhead
        return this.estimateMemoryUsage(params) * 1.5;
    }

    /**
     * Estimate training time
     */
    estimateTrainingTime(params) {
        const maxEpochs = Math.max(...params.training.epochs);
        const maxBatchSize = Math.max(...params.training.batchSize);
        const maxUnits = Math.max(...params.lstm.units);

        // Rough estimation based on complexity
        return maxEpochs * maxBatchSize * maxUnits * 0.001; // milliseconds per operation
    }

    /**
     * Get optimization suggestions
     */
    getOptimizationSuggestions(params) {
        const suggestions = [];

        // Memory optimization
        const memoryUsage = this.estimateMemoryUsage(params);
        if (memoryUsage > params.resources.maxMemory * 0.7) {
            suggestions.push({
                type: 'memory',
                priority: 'high',
                message: 'Consider reducing model size or batch size to optimize memory usage',
                actions: [
                    'Reduce LSTM units',
                    'Decrease batch size',
                    'Remove unnecessary layers'
                ]
            });
        }

        // Performance optimization
        const trainingTime = this.estimateTrainingTime(params);
        if (trainingTime > params.resources.timeout * 0.7) {
            suggestions.push({
                type: 'performance',
                priority: 'medium',
                message: 'Consider optimizing training configuration for better performance',
                actions: [
                    'Increase batch size',
                    'Adjust learning rate',
                    'Enable early stopping'
                ]
            });
        }

        // Architecture optimization
        const maxUnits = Math.max(...params.lstm.units);
        if (maxUnits > 256) {
            suggestions.push({
                type: 'architecture',
                priority: 'low',
                message: 'Consider simplifying model architecture',
                actions: [
                    'Reduce number of units',
                    'Use more efficient layer configurations',
                    'Add regularization'
                ]
            });
        }

        return suggestions;
    }
}

module.exports = HyperparameterValidator;
