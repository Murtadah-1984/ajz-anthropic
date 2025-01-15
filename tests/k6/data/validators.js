import { check } from 'k6';

/**
 * Validation rules for maintenance windows
 */
const rules = {
    environment: {
        allowed: ['prod', 'staging', 'dev', 'test'],
        message: 'Invalid environment'
    },
    status: {
        allowed: ['pending', 'active', 'completed', 'cancelled', 'partially_completed'],
        message: 'Invalid status'
    },
    duration: {
        min: 1,
        max: 72,
        message: 'Duration must be between 1 and 72 hours'
    },
    comment: {
        minLength: 10,
        maxLength: 500,
        message: 'Comment must be between 10 and 500 characters'
    }
};

/**
 * Validate a single maintenance window
 */
export function validateMaintenanceWindow(window) {
    const errors = [];

    // Required fields
    const requiredFields = ['environment', 'start_time', 'duration', 'comment', 'status'];
    requiredFields.forEach(field => {
        if (!window[field]) {
            errors.push(`Missing required field: ${field}`);
        }
    });

    // Environment validation
    if (!rules.environment.allowed.includes(window.environment)) {
        errors.push(`${rules.environment.message}: ${window.environment}`);
    }

    // Status validation
    if (!rules.status.allowed.includes(window.status)) {
        errors.push(`${rules.status.message}: ${window.status}`);
    }

    // Duration validation
    if (window.duration < rules.duration.min || window.duration > rules.duration.max) {
        errors.push(rules.duration.message);
    }

    // Comment validation
    if (window.comment.length < rules.comment.minLength || window.comment.length > rules.comment.maxLength) {
        errors.push(rules.comment.message);
    }

    // Start time validation
    try {
        const startTime = new Date(window.start_time);
        if (isNaN(startTime.getTime())) {
            errors.push('Invalid start_time format');
        }
    } catch (e) {
        errors.push('Invalid start_time');
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Validate extension data
 */
export function validateExtensionData(data) {
    const errors = [];

    // Duration validation
    if (!data.duration || data.duration < 1 || data.duration > 24) {
        errors.push('Extension duration must be between 1 and 24 hours');
    }

    // Reason validation
    if (!data.reason || data.reason.length < 10 || data.reason.length > 500) {
        errors.push('Extension reason must be between 10 and 500 characters');
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Validate completion data
 */
export function validateCompletionData(data) {
    const errors = [];

    // Status validation
    if (!['completed', 'partially_completed', 'cancelled'].includes(data.completion_status)) {
        errors.push('Invalid completion status');
    }

    // Reason validation
    if (!data.reason || data.reason.length < 10 || data.reason.length > 500) {
        errors.push('Completion reason must be between 10 and 500 characters');
    }

    // Validate additional fields for partially completed status
    if (data.completion_status === 'partially_completed') {
        if (!data.completion_notes || data.completion_notes.length < 10) {
            errors.push('Completion notes required for partially completed status');
        }

        if (!Array.isArray(data.remaining_tasks) || data.remaining_tasks.length === 0) {
            errors.push('Remaining tasks required for partially completed status');
        } else {
            data.remaining_tasks.forEach((task, index) => {
                if (!task.description || task.description.length < 5) {
                    errors.push(`Invalid description for remaining task ${index + 1}`);
                }
                if (!['high', 'medium', 'low'].includes(task.priority)) {
                    errors.push(`Invalid priority for remaining task ${index + 1}`);
                }
            });
        }

        if (data.follow_up_required) {
            try {
                const followUpDate = new Date(data.follow_up_date);
                if (followUpDate <= new Date()) {
                    errors.push('Follow-up date must be in the future');
                }
            } catch (e) {
                errors.push('Invalid follow-up date format');
            }
        }
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Validate a batch of maintenance windows
 */
export function validateMaintenanceWindows(windows) {
    const errors = [];
    const timeMap = new Map();

    windows.forEach((window, index) => {
        // Validate individual window
        const { isValid, errors: windowErrors } = validateMaintenanceWindow(window);
        if (!isValid) {
            errors.push(`Window ${index + 1}: ${windowErrors.join(', ')}`);
        }

        // Check for time conflicts within same environment
        const startTime = new Date(window.start_time);
        const endTime = new Date(startTime.getTime() + window.duration * 3600000);
        const key = window.environment;

        if (!timeMap.has(key)) {
            timeMap.set(key, []);
        }

        const envWindows = timeMap.get(key);
        const hasConflict = envWindows.some(w => {
            const wStart = new Date(w.start_time);
            const wEnd = new Date(wStart.getTime() + w.duration * 3600000);
            return (startTime < wEnd && endTime > wStart);
        });

        if (hasConflict) {
            errors.push(`Window ${index + 1}: Time conflict detected in ${window.environment} environment`);
        }

        envWindows.push(window);
    });

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Validate test scenarios
 */
export function validateTestScenarios(scenarios) {
    const errors = [];

    // Validate normal operations
    const { errors: normalOpsErrors } = validateMaintenanceWindows(scenarios.normal_operations);
    if (normalOpsErrors.length > 0) {
        errors.push('Normal operations:', ...normalOpsErrors);
    }

    // Validate high load period
    const { errors: highLoadErrors } = validateMaintenanceWindows(scenarios.high_load_period);
    if (highLoadErrors.length > 0) {
        errors.push('High load period:', ...highLoadErrors);
    }

    // Validate concurrent maintenance
    const { errors: concurrentErrors } = validateMaintenanceWindows(scenarios.concurrent_maintenance);
    if (concurrentErrors.length > 0) {
        errors.push('Concurrent maintenance:', ...concurrentErrors);
    }

    // Validate mixed environments
    const { errors: mixedEnvErrors } = validateMaintenanceWindows(scenarios.mixed_environments);
    if (mixedEnvErrors.length > 0) {
        errors.push('Mixed environments:', ...mixedEnvErrors);
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

/**
 * Validate complete test dataset
 */
export function validateLoadTestData(data) {
    const errors = [];

    // Validate scenarios
    const { errors: scenarioErrors } = validateTestScenarios(data.scenarios);
    if (scenarioErrors.length > 0) {
        errors.push('Scenario validation errors:', ...scenarioErrors);
    }

    // Validate test cases
    Object.entries(data.testCases).forEach(([name, windows]) => {
        const { errors: testCaseErrors } = validateMaintenanceWindows(windows);
        if (testCaseErrors.length > 0) {
            errors.push(`Test case ${name}:`, ...testCaseErrors);
        }
    });

    // Validate extensions
    data.extensions.forEach((extension, index) => {
        const { errors: extensionErrors } = validateExtensionData(extension);
        if (extensionErrors.length > 0) {
            errors.push(`Extension ${index + 1}:`, ...extensionErrors);
        }
    });

    // Validate completions
    data.completions.forEach((completion, index) => {
        const { errors: completionErrors } = validateCompletionData(completion);
        if (completionErrors.length > 0) {
            errors.push(`Completion ${index + 1}:`, ...completionErrors);
        }
    });

    return {
        isValid: errors.length === 0,
        errors
    };
}
