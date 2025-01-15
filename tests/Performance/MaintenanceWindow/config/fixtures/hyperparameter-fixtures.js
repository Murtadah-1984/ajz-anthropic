/**
 * Test fixtures for hyperparameter validation
 */
module.exports = {
    // Valid configurations
    valid: {
        basic: {
            lstm: {
                units: [32, 64, 128],
                layers: [1, 2, 3],
                dropout: [0.1, 0.2, 0.3]
            },
            optimizer: {
                type: ['adam'],
                learningRate: [0.0001, 0.001, 0.01],
                beta1: [0.9],
                beta2: [0.999]
            },
            training: {
                epochs: [50, 100, 150],
                batchSize: [16, 32, 64]
            },
            resources: {
                maxMemory: 1024 * 1024 * 1024,
                maxGPUMemory: 1024 * 1024 * 1024,
                timeout: 3600
            }
        },
        minimal: {
            lstm: {
                units: [32],
                layers: [1],
                dropout: [0.1]
            },
            optimizer: {
                type: ['sgd'],
                learningRate: [0.001]
            },
            training: {
                epochs: [50],
                batchSize: [32]
            }
        },
        comprehensive: {
            lstm: {
                units: [32, 64, 128, 256],
                layers: [1, 2, 3],
                dropout: [0.1, 0.2, 0.3, 0.4],
                recurrentDropout: [0.0, 0.1],
                activation: ['tanh', 'relu'],
                recurrentActivation: ['sigmoid']
            },
            optimizer: {
                type: ['adam', 'rmsprop'],
                learningRate: [0.0001, 0.001],
                beta1: [0.9],
                beta2: [0.999],
                epsilon: [1e-7]
            },
            training: {
                epochs: [50, 100],
                batchSize: [32, 64],
                validationSplit: [0.2],
                shuffle: [true]
            },
            architecture: {
                windowSize: [24, 48],
                denseUnits: [1, 8],
                bidirectional: [false, true]
            }
        }
    },

    // Invalid configurations
    invalid: {
        missingRequired: {
            optimizer: {
                learningRate: [0.001]
            },
            training: {
                epochs: [100]
            }
        },
        invalidRanges: {
            lstm: {
                units: [-1, 0, 1000],
                layers: [0, 10],
                dropout: [-0.1, 1.5]
            },
            optimizer: {
                learningRate: [0, 2],
                beta1: [-0.1, 1.1]
            }
        },
        invalidTypes: {
            lstm: {
                units: "32",
                layers: 2,
                dropout: 0.2
            },
            optimizer: {
                learningRate: 0.001
            }
        },
        unsortedArrays: {
            lstm: {
                units: [128, 64, 32],
                layers: [3, 1, 2],
                dropout: [0.3, 0.1, 0.2]
            }
        }
    },

    // Resource configurations
    resources: {
        exceeded: {
            lstm: {
                units: [512],
                layers: [4],
                dropout: [0.5]
            },
            training: {
                epochs: [1000],
                batchSize: [512]
            },
            resources: {
                maxMemory: 512 * 1024 * 1024,
                timeout: 1800
            }
        },
        optimal: {
            lstm: {
                units: [32, 64],
                layers: [1, 2],
                dropout: [0.1, 0.2]
            },
            training: {
                epochs: [50, 100],
                batchSize: [32, 64]
            },
            resources: {
                maxMemory: 2 * 1024 * 1024 * 1024,
                timeout: 7200
            }
        }
    },

    // Warning configurations
    warnings: {
        complexArchitecture: {
            lstm: {
                units: [256, 512],
                layers: [3, 4],
                dropout: [0.1]
            }
        },
        extremeLearningRates: {
            optimizer: {
                type: ['adam'],
                learningRate: [1e-6, 0.5],
                beta1: [0.9],
                beta2: [0.999]
            }
        },
        highEpochCount: {
            training: {
                epochs: [500, 1000],
                batchSize: [32]
            }
        }
    },

    // Optimization test cases
    optimization: {
        memoryIntensive: {
            lstm: {
                units: [512],
                layers: [3]
            },
            resources: {
                maxMemory: 1024 * 1024 * 1024
            }
        },
        performanceIssues: {
            training: {
                epochs: [1000],
                batchSize: [16]
            },
            resources: {
                timeout: 3600
            }
        },
        complexArchitecture: {
            lstm: {
                units: [512],
                layers: [4]
            }
        }
    },

    // Edge cases
    edge: {
        emptyArrays: {
            lstm: {
                units: [],
                layers: [],
                dropout: []
            },
            optimizer: {
                learningRate: []
            }
        },
        minimumValues: {
            lstm: {
                units: [1],
                layers: [1],
                dropout: [0]
            },
            optimizer: {
                learningRate: [1e-6]
            }
        },
        maximumValues: {
            lstm: {
                units: [512],
                layers: [5],
                dropout: [0.9]
            },
            optimizer: {
                learningRate: [1.0]
            }
        }
    },

    // Combination test cases
    combinations: {
        resourceIntensive: {
            lstm: {
                units: [512],
                layers: [4],
                dropout: [0.5]
            },
            training: {
                epochs: [1000],
                batchSize: [256]
            },
            optimizer: {
                type: ['adam'],
                learningRate: [0.001]
            }
        },
        lightWeight: {
            lstm: {
                units: [32],
                layers: [1],
                dropout: [0.1]
            },
            training: {
                epochs: [50],
                batchSize: [16]
            },
            optimizer: {
                type: ['sgd'],
                learningRate: [0.01]
            }
        }
    },

    // Validation test cases
    validation: {
        crossValidation: {
            training: {
                epochs: [100],
                batchSize: [32],
                validationSplit: [0.2]
            },
            validation: {
                crossValidation: {
                    folds: 5,
                    shuffle: true
                }
            }
        },
        earlyStoppingHigh: {
            training: {
                epochs: [50],
                batchSize: [32]
            },
            validation: {
                earlyStoping: {
                    patience: 20
                }
            }
        }
    }
};
