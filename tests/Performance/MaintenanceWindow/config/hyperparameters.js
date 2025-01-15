/**
 * Model hyperparameters configuration
 */
module.exports = {
    // Default parameters
    default: {
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
    },

    // LSTM layer parameters
    lstm: {
        units: [32, 50, 64, 128],
        layers: [1, 2, 3],
        dropout: [0.1, 0.2, 0.3, 0.4],
        recurrentDropout: [0.0, 0.1, 0.2],
        activation: ['tanh', 'relu'],
        recurrentActivation: ['sigmoid', 'hard_sigmoid'],
        kernelRegularizer: {
            l1: [0.0, 0.01],
            l2: [0.0, 0.01]
        }
    },

    // Optimizer parameters
    optimizer: {
        type: ['adam', 'rmsprop', 'sgd'],
        learningRate: [0.0001, 0.001, 0.01],
        momentum: [0.0, 0.9],
        beta1: [0.9],
        beta2: [0.999],
        epsilon: [1e-7],
        decay: [0.0, 0.1]
    },

    // Training parameters
    training: {
        epochs: [50, 100, 150],
        batchSize: [16, 32, 64],
        validationSplit: [0.1, 0.2],
        shuffle: [true],
        earlyStoppingPatience: [5, 10],
        reduceLRPatience: [3, 5]
    },

    // Architecture parameters
    architecture: {
        windowSize: [12, 24, 48],
        denseUnits: [1, 8, 16],
        bidirectional: [false, true],
        batchNormalization: [false, true]
    },

    // Search strategy parameters
    search: {
        // Grid search parameters
        grid: {
            maxIterations: 100,
            minImprovement: 0.01
        },

        // Random search parameters
        random: {
            trials: 50,
            seed: 42
        },

        // Bayesian optimization parameters
        bayesian: {
            iterations: 30,
            initPoints: 5,
            explorationFactor: 0.1
        }
    },

    // Validation parameters
    validation: {
        // Cross-validation parameters
        crossValidation: {
            folds: 5,
            shuffle: true
        },

        // Early stopping parameters
        earlyStoping: {
            monitor: 'val_loss',
            minDelta: 0.001,
            patience: 10,
            mode: 'min'
        },

        // Learning rate schedule parameters
        learningRateSchedule: {
            monitor: 'val_loss',
            factor: 0.1,
            patience: 5,
            minLR: 0.00001
        }
    },

    // Model specific parameters
    models: {
        // LSTM specific parameters
        lstm: {
            stateful: false,
            returnSequences: true,
            goBackwards: false,
            unroll: false
        },

        // Dense layer parameters
        dense: {
            activation: ['linear', 'relu'],
            kernelInitializer: 'glorot_uniform',
            biasInitializer: 'zeros'
        },

        // Dropout parameters
        dropout: {
            spatial: false,
            noise_shape: null
        }
    },

    // Regularization parameters
    regularization: {
        // L1/L2 regularization
        kernel: {
            l1: [0.0, 0.01, 0.1],
            l2: [0.0, 0.01, 0.1]
        },

        // Activity regularization
        activity: {
            l1: [0.0, 0.01],
            l2: [0.0, 0.01]
        },

        // Recurrent regularization
        recurrent: {
            l1: [0.0, 0.01],
            l2: [0.0, 0.01]
        }
    },

    // Initialization parameters
    initialization: {
        kernel: [
            'glorot_uniform',
            'glorot_normal',
            'he_uniform',
            'he_normal'
        ],
        recurrent: [
            'orthogonal',
            'uniform',
            'normal'
        ],
        bias: [
            'zeros',
            'ones',
            'random_normal'
        ]
    },

    // Constraints parameters
    constraints: {
        maxNorm: [null, 3, 4, 5],
        minMaxNorm: [null, { min_value: 0.0, max_value: 1.0 }],
        nonNeg: [false, true],
        unitNorm: [false, true]
    },

    // Performance thresholds
    thresholds: {
        minAccuracy: 0.8,
        maxLoss: 0.2,
        minImprovement: 0.01,
        maxTrainingTime: 3600, // seconds
        minEpochs: 10,
        maxEpochs: 200,
        validationFrequency: 1
    },

    // Resource constraints
    resources: {
        maxMemory: 1024 * 1024 * 1024, // 1GB
        maxGPUMemory: 1024 * 1024 * 1024, // 1GB
        batchSizeLimit: 128,
        maxThreads: 4,
        timeout: 3600 // seconds
    }
};
