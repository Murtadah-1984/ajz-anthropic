const fs = require('fs');
const path = require('path');
const os = require('os');

/**
 * Test helper functions
 */
class TestHelpers {
    /**
     * Create temporary test directory
     */
    static createTempDir() {
        return fs.mkdtempSync(path.join(os.tmpdir(), 'config-validator-test-'));
    }

    /**
     * Write config to file
     */
    static writeConfig(config, configPath) {
        fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
        return configPath;
    }

    /**
     * Create test directories
     */
    static createTestDirs(basePath, dirs) {
        dirs.forEach(dir => {
            const fullPath = path.join(basePath, dir);
            if (!fs.existsSync(fullPath)) {
                fs.mkdirSync(fullPath, { recursive: true });
            }
        });
    }

    /**
     * Clean up test directories
     */
    static cleanup(dir) {
        if (fs.existsSync(dir)) {
            fs.rmSync(dir, { recursive: true, force: true });
        }
    }

    /**
     * Create test environment
     */
    static setupTestEnv() {
        const tempDir = this.createTempDir();
        const configPath = path.join(tempDir, 'config.json');

        // Create common test directories
        this.createTestDirs(tempDir, [
            'charts',
            'logs',
            'output'
        ]);

        return {
            tempDir,
            configPath,
            cleanup: () => this.cleanup(tempDir)
        };
    }

    /**
     * Validate error contains
     */
    static validateErrorContains(errors, section, path, messagePattern) {
        return errors.some(error =>
            error.section === section &&
            error.path === path &&
            error.message.match(messagePattern)
        );
    }

    /**
     * Validate warning contains
     */
    static validateWarningContains(warnings, pattern) {
        return warnings.some(warning => warning.match(pattern));
    }

    /**
     * Deep clone object
     */
    static cloneConfig(config) {
        return JSON.parse(JSON.stringify(config));
    }

    /**
     * Modify config path
     */
    static modifyConfigPaths(config, tempDir) {
        const modifiedConfig = this.cloneConfig(config);

        if (modifiedConfig.visualize?.outputDir) {
            modifiedConfig.visualize.outputDir = path.join(tempDir, modifiedConfig.visualize.outputDir);
        }

        if (modifiedConfig.logging?.file) {
            modifiedConfig.logging.file = path.join(tempDir, modifiedConfig.logging.file);
        }

        return modifiedConfig;
    }

    /**
     * Create test context
     */
    static createTestContext(config, environment = null) {
        const { tempDir, configPath, cleanup } = this.setupTestEnv();
        const modifiedConfig = this.modifyConfigPaths(config, tempDir);
        this.writeConfig(modifiedConfig, configPath);

        return {
            tempDir,
            configPath,
            config: modifiedConfig,
            environment,
            cleanup
        };
    }

    /**
     * Validate test results
     */
    static validateResults(results, expectedErrors = [], expectedWarnings = []) {
        const isValid = expectedErrors.length === 0;

        expect(results.isValid).toBe(isValid);

        if (expectedErrors.length > 0) {
            expectedErrors.forEach(({ section, path, pattern }) => {
                expect(this.validateErrorContains(results.errors, section, path, pattern)).toBe(true);
            });
        } else {
            expect(results.errors).toHaveLength(0);
        }

        if (expectedWarnings.length > 0) {
            expectedWarnings.forEach(pattern => {
                expect(this.validateWarningContains(results.warnings, pattern)).toBe(true);
            });
        }
    }

    /**
     * Run validation test
     */
    static runValidationTest(config, environment = null, expectedErrors = [], expectedWarnings = []) {
        const { configPath, cleanup } = this.createTestContext(config, environment);

        try {
            const results = ConfigValidator.validate(configPath, environment);
            this.validateResults(results, expectedErrors, expectedWarnings);
        } finally {
            cleanup();
        }
    }
}

module.exports = TestHelpers;
