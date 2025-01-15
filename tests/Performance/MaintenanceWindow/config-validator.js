const Joi = require('joi');
const fs = require('fs');
const path = require('path');
const chalk = require('chalk');

/**
 * Configuration schema definitions
 */
const schemas = {
    visualize: Joi.object({
        outputDir: Joi.string().required(),
        defaultFormat: Joi.string().valid('html', 'png', 'both').required(),
        chartSize: Joi.object({
            width: Joi.number().min(100).max(2000).required(),
            height: Joi.number().min(100).max(2000).required()
        }).required(),
        autoRefresh: Joi.boolean(),
        refreshInterval: Joi.number().min(1).when('autoRefresh', {
            is: true,
            then: Joi.required()
        }),
        colors: Joi.object({
            primary: Joi.string().pattern(/^rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\)$/).required(),
            secondary: Joi.string().pattern(/^rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\)$/).required(),
            tertiary: Joi.string().pattern(/^rgb\(\d{1,3},\s*\d{1,3},\s*\d{1,3}\)$/)
        }).required(),
        tooltips: Joi.object({
            enabled: Joi.boolean().required(),
            detailed: Joi.boolean()
        })
    }),

    analyze: Joi.object({
        defaultType: Joi.string().valid('basic', 'detailed', 'comparison').required(),
        thresholds: Joi.object({
            executionTime: Joi.number().min(0).required(),
            memoryUsage: Joi.number().min(0).required(),
            throughput: Joi.number().min(0).required(),
            errorRate: Joi.number().min(0).max(1),
            p95ResponseTime: Joi.number().min(0),
            p99ResponseTime: Joi.number().min(0)
        }).required(),
        alerts: Joi.object({
            enabled: Joi.boolean().required(),
            performance: Joi.when('enabled', {
                is: true,
                then: Joi.object({
                    warning: Joi.number().min(0).max(1).required(),
                    critical: Joi.number().min(0).max(1).required()
                }).required()
            }),
            memory: Joi.when('enabled', {
                is: true,
                then: Joi.object({
                    warning: Joi.number().min(0).max(1).required(),
                    critical: Joi.number().min(0).max(1).required()
                }).required()
            })
        })
    }),

    watch: Joi.object({
        interval: Joi.number().min(1).required(),
        autoGenerate: Joi.boolean().required(),
        notifications: Joi.boolean().required(),
        retention: Joi.object({
            days: Joi.number().min(1).required(),
            maxFiles: Joi.number().min(1).required()
        }).required()
    }),

    reporting: Joi.object({
        format: Joi.string().valid('html', 'json', 'both').required(),
        includeSystemInfo: Joi.boolean().required(),
        includeTrends: Joi.boolean().required(),
        includeDebugInfo: Joi.boolean(),
        groupBy: Joi.string().valid('environment', 'feature', 'status').required(),
        metrics: Joi.array().items(Joi.string()).min(1).required()
    }),

    logging: Joi.object({
        level: Joi.string().valid('debug', 'info', 'warn', 'error').required(),
        file: Joi.string().required(),
        format: Joi.string().valid('json', 'text', 'detailed').required(),
        rotation: Joi.object({
            enabled: Joi.boolean().required(),
            maxSize: Joi.string().pattern(/^\d+[MGK]B?$/).when('enabled', {
                is: true,
                then: Joi.required()
            }),
            maxFiles: Joi.number().min(1).when('enabled', {
                is: true,
                then: Joi.required()
            })
        }).required()
    })
};

/**
 * Environment-specific schema additions
 */
const environmentSchemas = {
    development: Joi.object({
        debug: Joi.object({
            enabled: Joi.boolean().required(),
            logLevel: Joi.string().valid('verbose', 'debug').when('enabled', {
                is: true,
                then: Joi.required()
            }),
            stackTraces: Joi.boolean(),
            timings: Joi.boolean(),
            memoryProfiling: Joi.boolean()
        })
    }),

    production: Joi.object({
        monitoring: Joi.object({
            enabled: Joi.boolean().required(),
            prometheus: Joi.object({
                enabled: Joi.boolean().required(),
                endpoint: Joi.string().when('enabled', {
                    is: true,
                    then: Joi.required()
                })
            }),
            grafana: Joi.object({
                enabled: Joi.boolean().required(),
                dashboards: Joi.array().items(Joi.string()).when('enabled', {
                    is: true,
                    then: Joi.required()
                })
            })
        }).required(),
        security: Joi.object({
            dataRetention: Joi.object({
                enabled: Joi.boolean().required(),
                duration: Joi.string().pattern(/^\d+[dmy]$/).required(),
                archival: Joi.boolean().required()
            }).required(),
            encryption: Joi.object({
                enabled: Joi.boolean().required(),
                algorithm: Joi.string().when('enabled', {
                    is: true,
                    then: Joi.required()
                })
            }).required()
        }).required()
    })
};

/**
 * Configuration validator class
 */
class ConfigValidator {
    /**
     * Validate configuration file
     */
    static validate(configPath, environment = null) {
        try {
            const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
            const results = {
                isValid: true,
                errors: [],
                warnings: []
            };

            // Validate each section
            Object.entries(schemas).forEach(([section, schema]) => {
                if (config[section]) {
                    const { error } = schema.validate(config[section], { abortEarly: false });
                    if (error) {
                        results.isValid = false;
                        results.errors.push(...this.formatValidationErrors(section, error));
                    }
                } else {
                    results.warnings.push(`Missing section: ${section}`);
                }
            });

            // Validate environment-specific schema if provided
            if (environment && environmentSchemas[environment]) {
                const { error } = environmentSchemas[environment].validate(
                    config[environment] || {},
                    { abortEarly: false }
                );
                if (error) {
                    results.isValid = false;
                    results.errors.push(
                        ...this.formatValidationErrors(environment, error)
                    );
                }
            }

            // Additional validation checks
            this.validateRelationships(config, results);
            this.validatePaths(config, configPath, results);
            this.validateSecurity(config, results);

            return results;
        } catch (error) {
            return {
                isValid: false,
                errors: [`Failed to parse config file: ${error.message}`],
                warnings: []
            };
        }
    }

    /**
     * Format validation errors
     */
    static formatValidationErrors(section, error) {
        return error.details.map(detail => ({
            section,
            path: detail.path.join('.'),
            message: detail.message
        }));
    }

    /**
     * Validate relationships between config sections
     */
    static validateRelationships(config, results) {
        // Check if debug features are enabled in production
        if (config.production && config.analyze?.debug?.enabled) {
            results.warnings.push(
                'Debug features are enabled in production configuration'
            );
        }

        // Verify monitoring configuration when alerts are enabled
        if (config.analyze?.alerts?.enabled && !config.monitoring?.enabled) {
            results.warnings.push(
                'Alerts are enabled but monitoring is not configured'
            );
        }

        // Check retention policy consistency
        if (config.watch?.retention && config.logging?.rotation) {
            const watchDays = config.watch.retention.days;
            const loggingFiles = config.logging.rotation.maxFiles;
            if (watchDays * 24 > loggingFiles) {
                results.warnings.push(
                    'Watch retention period exceeds log rotation capacity'
                );
            }
        }
    }

    /**
     * Validate paths in configuration
     */
    static validatePaths(config, configPath, results) {
        const basePath = path.dirname(configPath);

        // Validate output directory
        if (config.visualize?.outputDir) {
            const outputPath = path.resolve(basePath, config.visualize.outputDir);
            try {
                fs.accessSync(path.dirname(outputPath));
            } catch (error) {
                results.warnings.push(
                    `Output directory parent does not exist: ${outputPath}`
                );
            }
        }

        // Validate log file path
        if (config.logging?.file) {
            const logPath = path.resolve(basePath, config.logging.file);
            try {
                fs.accessSync(path.dirname(logPath));
            } catch (error) {
                results.warnings.push(
                    `Log file directory does not exist: ${logPath}`
                );
            }
        }
    }

    /**
     * Validate security settings
     */
    static validateSecurity(config, results) {
        // Check for sensitive information in non-production environments
        if (!config.production && config.security?.encryption?.enabled) {
            results.warnings.push(
                'Encryption is enabled in non-production environment'
            );
        }

        // Verify secure defaults in production
        if (config.production) {
            if (!config.security?.encryption?.enabled) {
                results.errors.push('Encryption must be enabled in production');
            }
            if (!config.security?.access?.requireAuth) {
                results.errors.push('Authentication must be required in production');
            }
        }
    }

    /**
     * Print validation results
     */
    static printResults(results) {
        console.log('\nConfiguration Validation Results:');
        console.log('================================');

        if (results.isValid) {
            console.log(chalk.green('✓ Configuration is valid\n'));
        } else {
            console.log(chalk.red('✗ Configuration is invalid\n'));
        }

        if (results.errors.length > 0) {
            console.log(chalk.red('Errors:'));
            results.errors.forEach(error => {
                console.log(chalk.red(`  • [${error.section}] ${error.path}: ${error.message}`));
            });
            console.log();
        }

        if (results.warnings.length > 0) {
            console.log(chalk.yellow('Warnings:'));
            results.warnings.forEach(warning => {
                console.log(chalk.yellow(`  • ${warning}`));
            });
            console.log();
        }

        return results.isValid;
    }
}

// Run validation if called directly
if (require.main === module) {
    const configPath = process.argv[2];
    const environment = process.argv[3];

    if (!configPath) {
        console.error('Usage: node config-validator.js <config-path> [environment]');
        process.exit(1);
    }

    const results = ConfigValidator.validate(configPath, environment);
    process.exit(ConfigValidator.printResults(results) ? 0 : 1);
}

module.exports = ConfigValidator;
