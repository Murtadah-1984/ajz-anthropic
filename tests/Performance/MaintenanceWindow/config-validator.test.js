const { describe, expect, beforeEach, afterEach } = require('@jest/globals');
const ConfigValidator = require('./config-validator');
const TestHelpers = require('./test-helpers');
const {
    validConfigs,
    invalidConfigs,
    warningConfigs,
    edgeCases
} = require('./fixtures/config.fixtures');

describe('ConfigValidator', () => {
    let testContext;

    beforeEach(() => {
        testContext = TestHelpers.setupTestEnv();
    });

    afterEach(() => {
        testContext.cleanup();
    });

    describe('Valid Configurations', () => {
        test('validates default configuration', () => {
            TestHelpers.runValidationTest(validConfigs.default);
        });

        test('validates development configuration', () => {
            TestHelpers.runValidationTest(validConfigs.development, 'development');
        });

        test('validates production configuration', () => {
            TestHelpers.runValidationTest(validConfigs.production, 'production');
        });
    });

    describe('Invalid Configurations', () => {
        test('detects missing required fields', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.missingRequired,
                null,
                [{
                    section: 'visualize',
                    path: 'outputDir',
                    pattern: /required/
                }]
            );
        });

        test('validates field types', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.invalidTypes,
                null,
                [{
                    section: 'analyze',
                    path: 'thresholds.executionTime',
                    pattern: /must be a number/
                }]
            );
        });

        test('validates paths', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.invalidPaths,
                null,
                [],
                ['Output directory parent does not exist']
            );
        });

        test('detects debug in production', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.debugInProduction,
                'production',
                [],
                ['Debug features are enabled in production configuration']
            );
        });

        test('validates retention policies', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.retentionMismatch,
                null,
                [],
                ['Watch retention period exceeds log rotation capacity']
            );
        });

        test('requires secure settings in production', () => {
            TestHelpers.runValidationTest(
                invalidConfigs.insecureProduction,
                'production',
                [{
                    section: 'production',
                    path: 'security.encryption',
                    pattern: /must be enabled/
                }]
            );
        });
    });

    describe('Warning Configurations', () => {
        test('warns about encryption in development', () => {
            TestHelpers.runValidationTest(
                warningConfigs.encryptionInDev,
                'development',
                [],
                ['Encryption is enabled in non-production environment']
            );
        });

        test('warns about missing monitoring', () => {
            TestHelpers.runValidationTest(
                warningConfigs.missingMonitoring,
                null,
                [],
                ['Alerts are enabled but monitoring is not configured']
            );
        });

        test('warns about excessive retention', () => {
            TestHelpers.runValidationTest(
                warningConfigs.excessiveRetention,
                null,
                [],
                ['Watch retention period exceeds log rotation capacity']
            );
        });
    });

    describe('Edge Cases', () => {
        test('handles empty configuration', () => {
            TestHelpers.runValidationTest(
                edgeCases.emptyConfig,
                null,
                [],
                [
                    'Missing section: visualize',
                    'Missing section: analyze',
                    'Missing section: watch',
                    'Missing section: reporting',
                    'Missing section: logging'
                ]
            );
        });

        test('validates minimal configuration', () => {
            TestHelpers.runValidationTest(edgeCases.minimalConfig);
        });

        test('validates maximal configuration', () => {
            TestHelpers.runValidationTest(edgeCases.maximalConfig);
        });

        test('validates boundary values', () => {
            TestHelpers.runValidationTest(edgeCases.boundaryValues);
        });
    });

    describe('Environment-Specific Validation', () => {
        test('validates development-specific features', () => {
            const config = {
                ...validConfigs.default,
                development: {
                    debug: {
                        enabled: true,
                        logLevel: 'invalid' // Should be 'verbose' or 'debug'
                    }
                }
            };

            TestHelpers.runValidationTest(
                config,
                'development',
                [{
                    section: 'development',
                    path: 'debug.logLevel',
                    pattern: /must be one of/
                }]
            );
        });

        test('validates production security requirements', () => {
            const config = {
                ...validConfigs.default,
                production: {
                    monitoring: {
                        enabled: true,
                        prometheus: { enabled: false },
                        grafana: { enabled: false }
                    }
                    // Missing required security section
                }
            };

            TestHelpers.runValidationTest(
                config,
                'production',
                [{
                    section: 'production',
                    path: 'security',
                    pattern: /required/
                }]
            );
        });
    });

    describe('Path Resolution', () => {
        test('resolves relative paths', () => {
            const config = TestHelpers.cloneConfig(validConfigs.default);
            config.visualize.outputDir = './relative/path';

            const { configPath, cleanup } = TestHelpers.createTestContext(config);

            try {
                const results = ConfigValidator.validate(configPath);
                expect(results.warnings).toContain(
                    expect.stringContaining('Output directory parent does not exist')
                );
            } finally {
                cleanup();
            }
        });

        test('validates directory existence', () => {
            const { tempDir, configPath, cleanup } = TestHelpers.createTestContext(validConfigs.default);

            try {
                // Create the output directory
                TestHelpers.createTestDirs(tempDir, ['charts']);

                const results = ConfigValidator.validate(configPath);
                expect(results.warnings).not.toContain(
                    expect.stringContaining('Output directory parent does not exist')
                );
            } finally {
                cleanup();
            }
        });
    });

    describe('Error Formatting', () => {
        test('formats validation errors clearly', () => {
            const { configPath, cleanup } = TestHelpers.createTestContext(invalidConfigs.missingRequired);

            try {
                const results = ConfigValidator.validate(configPath);
                expect(results.errors[0]).toEqual(
                    expect.objectContaining({
                        section: expect.any(String),
                        path: expect.any(String),
                        message: expect.any(String)
                    })
                );
            } finally {
                cleanup();
            }
        });

        test('includes section and path in errors', () => {
            const { configPath, cleanup } = TestHelpers.createTestContext(invalidConfigs.invalidTypes);

            try {
                const results = ConfigValidator.validate(configPath);
                const error = results.errors[0];

                expect(error.section).toBe('analyze');
                expect(error.path).toBe('thresholds.executionTime');
                expect(error.message).toMatch(/must be a number/);
            } finally {
                cleanup();
            }
        });
    });
});
