# Configuration Validator Tests

Comprehensive test suite for the configuration validation system.

## Overview

The test suite validates configuration files for maintenance windows, ensuring they meet all requirements and constraints across different environments.

## Structure

```
MaintenanceWindow/
├── config-validator.js       # Main validator implementation
├── config-validator.test.js  # Test suite
├── test-helpers.js          # Test utilities
└── fixtures/
    └── config.fixtures.js    # Test data
```

## Test Categories

### 1. Valid Configurations
- Default configuration
- Development environment
- Production environment

### 2. Invalid Configurations
- Missing required fields
- Invalid field types
- Invalid paths
- Debug in production
- Retention policy mismatches
- Insecure production settings

### 3. Warning Configurations
- Encryption in development
- Missing monitoring
- Excessive retention periods

### 4. Edge Cases
- Empty configurations
- Minimal configurations
- Maximal configurations
- Boundary values

### 5. Environment-Specific
- Development features
- Production security requirements

### 6. Path Resolution
- Relative paths
- Directory existence

### 7. Error Formatting
- Error structure
- Error details

## Test Helpers

### TestHelpers Class

```javascript
const helpers = require('./test-helpers');

// Setup test environment
const context = helpers.setupTestEnv();

// Run validation test
helpers.runValidationTest(config, environment, expectedErrors, expectedWarnings);

// Create test context
const { tempDir, configPath, cleanup } = helpers.createTestContext(config);
```

Key Features:
- Environment setup/cleanup
- Config file management
- Directory creation
- Path modification
- Result validation

## Test Fixtures

### Configuration Types

```javascript
const { validConfigs, invalidConfigs, warningConfigs, edgeCases } = require('./fixtures/config.fixtures');

// Valid configurations
const defaultConfig = validConfigs.default;
const devConfig = validConfigs.development;
const prodConfig = validConfigs.production;

// Invalid configurations
const missingFields = invalidConfigs.missingRequired;
const invalidTypes = invalidConfigs.invalidTypes;

// Warning configurations
const encryptionInDev = warningConfigs.encryptionInDev;
const missingMonitoring = warningConfigs.missingMonitoring;

// Edge cases
const emptyConfig = edgeCases.emptyConfig;
const minimalConfig = edgeCases.minimalConfig;
```

## Best Practices

1. **Test Organization**
   - Group related tests using `describe` blocks
   - Use clear, descriptive test names
   - Follow the Arrange-Act-Assert pattern

2. **Resource Management**
   - Always cleanup temporary files
   - Use `beforeEach` and `afterEach` hooks
   - Handle cleanup in `try/finally` blocks

3. **Fixtures Usage**
   - Use predefined fixtures for common cases
   - Clone fixtures before modification
   - Keep fixtures focused and minimal

4. **Error Handling**
   - Test both success and failure cases
   - Validate error messages and structure
   - Include edge cases and boundary conditions

5. **Path Handling**
   - Use platform-independent paths
   - Handle relative and absolute paths
   - Validate directory existence

## Writing Tests

### Basic Test Structure

```javascript
describe('Feature', () => {
    let testContext;

    beforeEach(() => {
        testContext = TestHelpers.setupTestEnv();
    });

    afterEach(() => {
        testContext.cleanup();
    });

    test('should validate successfully', () => {
        TestHelpers.runValidationTest(
            validConfigs.default,
            null,
            [],  // Expected errors
            []   // Expected warnings
        );
    });
});
```

### Testing Invalid Cases

```javascript
test('should detect invalid configuration', () => {
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
```

### Testing Warnings

```javascript
test('should warn about potential issues', () => {
    TestHelpers.runValidationTest(
        warningConfigs.encryptionInDev,
        'development',
        [],
        ['Encryption is enabled in non-production environment']
    );
});
```

## Running Tests

```bash
# Run all tests
npm test

# Run specific test file
npm test config-validator.test.js

# Run with coverage
npm test -- --coverage
```

## Extending Tests

1. **Adding New Test Cases**
   - Add fixtures to `config.fixtures.js`
   - Create test in appropriate describe block
   - Use test helpers for consistency

2. **Adding New Validations**
   - Add validation to `config-validator.js`
   - Add corresponding test cases
   - Update fixtures if needed

3. **Adding New Helpers**
   - Add helper to `test-helpers.js`
   - Document helper functionality
   - Add usage examples

## Troubleshooting

Common issues and solutions:

1. **Cleanup Failures**
   ```javascript
   // Always use try/finally
   try {
       // Test code
   } finally {
       cleanup();
   }
   ```

2. **Path Resolution**
   ```javascript
   // Use platform-independent paths
   const configPath = path.join(tempDir, 'config.json');
   ```

3. **Test Isolation**
   ```javascript
   // Clone fixtures before modification
   const config = TestHelpers.cloneConfig(validConfigs.default);
   ```

## Contributing

1. Follow existing patterns and conventions
2. Add tests for new functionality
3. Update documentation
4. Run full test suite before submitting

## License

MIT License - see LICENSE file for details
