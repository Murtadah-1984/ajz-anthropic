/**
 * Test fixtures for configuration validation
 */

const validConfigs = {
    default: {
        visualize: {
            outputDir: './charts',
            defaultFormat: 'both',
            chartSize: {
                width: 800,
                height: 400
            },
            colors: {
                primary: 'rgb(75, 192, 192)',
                secondary: 'rgb(255, 99, 132)'
            },
            tooltips: {
                enabled: true
            }
        },
        analyze: {
            defaultType: 'detailed',
            thresholds: {
                executionTime: 500,
                memoryUsage: 100,
                throughput: 1000
            },
            alerts: {
                enabled: false
            }
        },
        watch: {
            interval: 5,
            autoGenerate: true,
            notifications: true,
            retention: {
                days: 30,
                maxFiles: 1000
            }
        },
        reporting: {
            format: 'html',
            includeSystemInfo: true,
            includeTrends: true,
            groupBy: 'environment',
            metrics: ['execution_time', 'memory_usage']
        },
        logging: {
            level: 'info',
            file: './logs/perf.log',
            format: 'json',
            rotation: {
                enabled: true,
                maxSize: '100MB',
                maxFiles: 10
            }
        }
    },

    development: {
        ...this.default,
        development: {
            debug: {
                enabled: true,
                logLevel: 'verbose',
                stackTraces: true,
                timings: true,
                memoryProfiling: true
            }
        },
        analyze: {
            defaultType: 'basic',
            thresholds: {
                executionTime: 1000,
                memoryUsage: 200,
                throughput: 500
            },
            alerts: {
                enabled: false
            },
            debug: {
                enabled: true,
                logLevel: 'verbose'
            }
        }
    },

    production: {
        ...this.default,
        production: {
            monitoring: {
                enabled: true,
                prometheus: {
                    enabled: true,
                    endpoint: '/metrics'
                },
                grafana: {
                    enabled: true,
                    dashboards: ['overview', 'errors', 'resources']
                }
            },
            security: {
                dataRetention: {
                    enabled: true,
                    duration: '90d',
                    archival: true
                },
                encryption: {
                    enabled: true,
                    algorithm: 'AES-256-GCM'
                }
            }
        },
        analyze: {
            defaultType: 'detailed',
            thresholds: {
                executionTime: 200,
                memoryUsage: 50,
                throughput: 2000,
                errorRate: 0.001
            },
            alerts: {
                enabled: true,
                performance: {
                    warning: 0.7,
                    critical: 0.8
                },
                memory: {
                    warning: 0.6,
                    critical: 0.75
                }
            }
        }
    }
};

const invalidConfigs = {
    missingRequired: {
        visualize: {
            defaultFormat: 'html'
            // Missing required outputDir
        }
    },

    invalidTypes: {
        analyze: {
            defaultType: 'detailed',
            thresholds: {
                executionTime: 'invalid', // Should be number
                memoryUsage: 100,
                throughput: 1000
            },
            alerts: {
                enabled: false
            }
        }
    },

    invalidPaths: {
        visualize: {
            outputDir: './nonexistent/charts',
            defaultFormat: 'both',
            chartSize: {
                width: 800,
                height: 400
            },
            colors: {
                primary: 'rgb(75, 192, 192)',
                secondary: 'rgb(255, 99, 132)'
            },
            tooltips: {
                enabled: true
            }
        }
    },

    debugInProduction: {
        production: {
            monitoring: {
                enabled: true,
                prometheus: { enabled: false },
                grafana: { enabled: false }
            },
            security: {
                dataRetention: {
                    enabled: true,
                    duration: '90d',
                    archival: true
                },
                encryption: {
                    enabled: true,
                    algorithm: 'AES-256-GCM'
                }
            }
        },
        analyze: {
            debug: {
                enabled: true
            }
        }
    },

    retentionMismatch: {
        watch: {
            retention: {
                days: 30,
                maxFiles: 1000
            }
        },
        logging: {
            level: 'info',
            file: './logs/perf.log',
            format: 'json',
            rotation: {
                enabled: true,
                maxSize: '100MB',
                maxFiles: 10
            }
        }
    },

    insecureProduction: {
        production: {
            monitoring: {
                enabled: true,
                prometheus: { enabled: false },
                grafana: { enabled: false }
            },
            security: {
                dataRetention: {
                    enabled: true,
                    duration: '90d',
                    archival: true
                },
                encryption: {
                    enabled: false // Should be true in production
                }
            }
        }
    }
};

const warningConfigs = {
    encryptionInDev: {
        security: {
            encryption: {
                enabled: true,
                algorithm: 'AES-256-GCM'
            }
        }
    },

    missingMonitoring: {
        analyze: {
            alerts: {
                enabled: true
            }
        }
    },

    excessiveRetention: {
        watch: {
            retention: {
                days: 90,
                maxFiles: 100
            }
        },
        logging: {
            rotation: {
                enabled: true,
                maxFiles: 10
            }
        }
    }
};

const edgeCases = {
    emptyConfig: {},

    minimalConfig: {
        visualize: {
            outputDir: './charts',
            defaultFormat: 'html',
            chartSize: {
                width: 100,
                height: 100
            },
            colors: {
                primary: 'rgb(0, 0, 0)',
                secondary: 'rgb(255, 255, 255)'
            },
            tooltips: {
                enabled: false
            }
        }
    },

    maximalConfig: {
        ...validConfigs.production,
        development: validConfigs.development.development,
        analyze: {
            ...validConfigs.production.analyze,
            debug: validConfigs.development.analyze.debug
        }
    },

    boundaryValues: {
        visualize: {
            outputDir: './charts',
            defaultFormat: 'both',
            chartSize: {
                width: 2000, // Maximum allowed
                height: 2000  // Maximum allowed
            },
            colors: {
                primary: 'rgb(255, 255, 255)',
                secondary: 'rgb(0, 0, 0)'
            },
            tooltips: {
                enabled: true
            }
        },
        analyze: {
            defaultType: 'detailed',
            thresholds: {
                executionTime: Number.MAX_SAFE_INTEGER,
                memoryUsage: Number.MAX_SAFE_INTEGER,
                throughput: Number.MAX_SAFE_INTEGER,
                errorRate: 1 // Maximum allowed
            },
            alerts: {
                enabled: true,
                performance: {
                    warning: 1, // Maximum allowed
                    critical: 1  // Maximum allowed
                }
            }
        }
    }
};

module.exports = {
    validConfigs,
    invalidConfigs,
    warningConfigs,
    edgeCases
};
