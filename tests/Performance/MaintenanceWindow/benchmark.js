const Benchmark = require('benchmark');
const ConfigValidator = require('./config-validator');
const TestHelpers = require('./test-helpers');
const {
    validConfigs,
    invalidConfigs,
    warningConfigs,
    edgeCases
} = require('./fixtures/config.fixtures');

/**
 * Performance benchmarking suite
 */
class ValidationBenchmark {
    constructor() {
        this.suite = new Benchmark.Suite;
        this.testContext = TestHelpers.setupTestEnv();
    }

    /**
     * Run all benchmarks
     */
    async run() {
        console.log('\nRunning Configuration Validator Benchmarks...\n');

        // Setup test data
        this.setupBenchmarkData();

        // Add test cases
        this.addValidationTests();
        this.addScalingTests();
        this.addComplexityTests();

        // Run benchmarks
        return new Promise((resolve, reject) => {
            this.suite
                .on('cycle', event => {
                    console.log(String(event.target));
                })
                .on('complete', () => {
                    this.printSummary();
                    this.cleanup();
                    resolve();
                })
                .on('error', error => {
                    this.cleanup();
                    reject(error);
                })
                .run({ async: true });
        });
    }

    /**
     * Setup benchmark data
     */
    setupBenchmarkData() {
        // Create test configs of different sizes
        this.configs = {
            small: this.generateConfig(10),
            medium: this.generateConfig(100),
            large: this.generateConfig(1000),
            complex: this.generateComplexConfig()
        };

        // Write configs to files
        this.configPaths = {};
        Object.entries(this.configs).forEach(([size, config]) => {
            this.configPaths[size] = TestHelpers.writeConfig(
                config,
                `${this.testContext.tempDir}/config-${size}.json`
            );
        });
    }

    /**
     * Generate test configuration
     */
    generateConfig(size) {
        const config = TestHelpers.cloneConfig(validConfigs.default);

        // Add multiple sections
        for (let i = 0; i < size; i++) {
            config[`section_${i}`] = {
                enabled: true,
                name: `Section ${i}`,
                options: {
                    setting1: `value${i}`,
                    setting2: i,
                    setting3: true
                }
            };
        }

        return config;
    }

    /**
     * Generate complex configuration
     */
    generateComplexConfig() {
        const config = TestHelpers.cloneConfig(validConfigs.production);

        // Add nested structures
        config.complex = {
            level1: {
                level2: {
                    level3: {
                        level4: {
                            settings: Array(100).fill(0).map((_, i) => ({
                                id: i,
                                name: `Setting ${i}`,
                                value: `value_${i}`,
                                enabled: i % 2 === 0,
                                priority: i % 3,
                                tags: [`tag${i}`, `category${i % 5}`]
                            }))
                        }
                    }
                }
            }
        };

        return config;
    }

    /**
     * Add validation benchmark tests
     */
    addValidationTests() {
        // Basic validation
        this.suite.add('Validate Default Config', () => {
            ConfigValidator.validate(this.configPaths.small);
        });

        // Environment validation
        this.suite.add('Validate Production Config', () => {
            ConfigValidator.validate(this.configPaths.small, 'production');
        });

        // Invalid config validation
        this.suite.add('Validate Invalid Config', () => {
            ConfigValidator.validate(
                TestHelpers.writeConfig(
                    invalidConfigs.missingRequired,
                    `${this.testContext.tempDir}/invalid.json`
                )
            );
        });
    }

    /**
     * Add scaling benchmark tests
     */
    addScalingTests() {
        // Test different sizes
        Object.entries(this.configPaths).forEach(([size, path]) => {
            this.suite.add(`Validate ${size} Config`, () => {
                ConfigValidator.validate(path);
            });
        });

        // Batch validation
        this.suite.add('Batch Validate 10 Configs', () => {
            for (let i = 0; i < 10; i++) {
                ConfigValidator.validate(this.configPaths.small);
            }
        });
    }

    /**
     * Add complexity benchmark tests
     */
    addComplexityTests() {
        // Deep nesting
        this.suite.add('Validate Deeply Nested Config', () => {
            ConfigValidator.validate(this.configPaths.complex);
        });

        // Many validations
        this.suite.add('Validate With Many Rules', () => {
            const config = this.generateConfig(10);
            config.rules = Array(1000).fill(0).map((_, i) => ({
                id: i,
                type: i % 3 === 0 ? 'error' : 'warning',
                condition: `condition_${i}`,
                message: `message_${i}`
            }));

            ConfigValidator.validate(
                TestHelpers.writeConfig(
                    config,
                    `${this.testContext.tempDir}/many-rules.json`
                )
            );
        });
    }

    /**
     * Print benchmark summary
     */
    printSummary() {
        console.log('\nBenchmark Summary:');
        console.log('==================\n');

        // Find fastest and slowest
        const fastest = this.suite.filter('fastest');
        const slowest = this.suite.filter('slowest');

        console.log('Fastest:', fastest.map('name').join(', '));
        console.log('Slowest:', slowest.map('name').join(', '));

        // Calculate statistics
        const stats = this.calculateStats();
        console.log('\nStatistics:');
        console.log(`Mean execution time: ${stats.mean.toFixed(4)}ms`);
        console.log(`Standard deviation: ${stats.stdDev.toFixed(4)}ms`);
        console.log(`95th percentile: ${stats.percentile95.toFixed(4)}ms`);

        // Print recommendations
        this.printRecommendations(stats);
    }

    /**
     * Calculate benchmark statistics
     */
    calculateStats() {
        const times = this.suite.map(benchmark => benchmark.stats.mean * 1000);

        return {
            mean: times.reduce((a, b) => a + b) / times.length,
            stdDev: Math.sqrt(
                times.reduce((sq, n) => sq + Math.pow(n - (times.reduce((a, b) => a + b) / times.length), 2), 0) /
                (times.length - 1)
            ),
            percentile95: times.sort((a, b) => a - b)[Math.floor(times.length * 0.95)]
        };
    }

    /**
     * Print performance recommendations
     */
    printRecommendations(stats) {
        console.log('\nRecommendations:');

        // Check mean execution time
        if (stats.mean > 100) {
            console.log('- Consider implementing caching for validation results');
            console.log('- Review validation rules complexity');
        }

        // Check standard deviation
        if (stats.stdDev > stats.mean * 0.5) {
            console.log('- High variance in execution times detected');
            console.log('- Consider implementing batch processing for large configs');
        }

        // Check 95th percentile
        if (stats.percentile95 > stats.mean * 2) {
            console.log('- Performance degradation detected for complex cases');
            console.log('- Consider implementing progressive validation');
        }
    }

    /**
     * Cleanup test resources
     */
    cleanup() {
        this.testContext.cleanup();
    }
}

// Run benchmarks if called directly
if (require.main === module) {
    const benchmark = new ValidationBenchmark();
    benchmark.run().catch(console.error);
}

module.exports = ValidationBenchmark;
