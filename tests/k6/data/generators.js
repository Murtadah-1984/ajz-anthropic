import { faker } from '@faker-js/faker';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Environment configurations
const environments = ['prod', 'staging', 'dev', 'test'];
const statuses = ['pending', 'active', 'completed', 'cancelled', 'partially_completed'];

/**
 * Generate a random maintenance window
 */
export function generateMaintenanceWindow(overrides = {}) {
    const startTime = new Date(Date.now() + randomIntBetween(3600000, 86400000)); // Between 1 hour and 1 day from now
    const duration = randomIntBetween(1, 8);

    return {
        environment: faker.helpers.arrayElement(environments),
        start_time: startTime.toISOString(),
        duration: duration,
        comment: faker.lorem.sentence(),
        status: faker.helpers.arrayElement(statuses),
        created_by: faker.number.int({ min: 1, max: 100 }),
        ...overrides
    };
}

/**
 * Generate a batch of maintenance windows
 */
export function generateMaintenanceWindows(count, options = {}) {
    const windows = [];
    const {
        environment,
        status,
        timeRange = { min: 0, max: 7 * 24 * 60 * 60 * 1000 } // Default: next 7 days
    } = options;

    for (let i = 0; i < count; i++) {
        const startTime = new Date(Date.now() + randomIntBetween(timeRange.min, timeRange.max));

        windows.push(generateMaintenanceWindow({
            environment: environment || faker.helpers.arrayElement(environments),
            status: status || faker.helpers.arrayElement(statuses),
            start_time: startTime.toISOString()
        }));
    }

    return windows;
}

/**
 * Generate test data for concurrent operations
 */
export function generateConcurrentScenario(options = {}) {
    const {
        windowCount = 50,
        environment = 'test',
        baseStartTime = new Date(Date.now() + 3600000), // 1 hour from now
        intervalMinutes = 30
    } = options;

    const windows = [];
    for (let i = 0; i < windowCount; i++) {
        const startTime = new Date(baseStartTime.getTime() + (i * intervalMinutes * 60000));

        windows.push(generateMaintenanceWindow({
            environment,
            start_time: startTime.toISOString(),
            status: 'active'
        }));
    }

    return windows;
}

/**
 * Generate extension data
 */
export function generateExtensionData(reason = null) {
    return {
        duration: randomIntBetween(1, 4),
        reason: reason || faker.helpers.arrayElement([
            'Additional tasks identified during maintenance',
            'System recovery taking longer than expected',
            'Verification steps pending completion',
            'Backup process running behind schedule',
            'Dependencies requiring additional configuration'
        ])
    };
}

/**
 * Generate completion data
 */
export function generateCompletionData(status = null) {
    const completionStatus = status || faker.helpers.arrayElement(['completed', 'partially_completed', 'cancelled']);

    const baseData = {
        reason: faker.lorem.sentence(),
        completion_status: completionStatus
    };

    if (completionStatus === 'partially_completed') {
        return {
            ...baseData,
            completion_notes: faker.lorem.paragraph(),
            remaining_tasks: Array.from({ length: randomIntBetween(1, 5) }, () => ({
                description: faker.lorem.sentence(),
                priority: faker.helpers.arrayElement(['high', 'medium', 'low']),
                estimated_duration: randomIntBetween(1, 4)
            })),
            follow_up_required: true,
            follow_up_date: new Date(Date.now() + randomIntBetween(86400000, 604800000)).toISOString() // 1-7 days from now
        };
    }

    return baseData;
}

/**
 * Generate realistic maintenance scenarios
 */
export function generateMaintenanceScenarios() {
    return {
        normal_operations: generateMaintenanceWindows(10, {
            environment: 'prod',
            timeRange: { min: 3600000, max: 86400000 } // Next 24 hours
        }),

        high_load_period: generateMaintenanceWindows(50, {
            environment: 'prod',
            timeRange: { min: 86400000, max: 172800000 } // 24-48 hours from now
        }),

        concurrent_maintenance: generateConcurrentScenario({
            windowCount: 20,
            environment: 'prod',
            intervalMinutes: 15
        }),

        mixed_environments: [
            ...generateMaintenanceWindows(5, { environment: 'prod' }),
            ...generateMaintenanceWindows(5, { environment: 'staging' }),
            ...generateMaintenanceWindows(5, { environment: 'dev' })
        ]
    };
}

/**
 * Generate test data for specific test cases
 */
export const testCases = {
    // Test case: Multiple windows ending at the same time
    concurrent_endings: () => {
        const baseTime = new Date(Date.now() + 3600000);
        return generateMaintenanceWindows(10, {
            status: 'active',
            timeRange: { min: 0, max: 300000 } // Within 5 minutes
        }).map(window => ({
            ...window,
            end_time: baseTime.toISOString()
        }));
    },

    // Test case: Windows with overlapping times
    overlapping_windows: () => {
        const windows = [];
        const baseTime = Date.now() + 3600000;

        for (let i = 0; i < 5; i++) {
            const startTime = new Date(baseTime + (i * 1800000)); // 30-minute intervals
            windows.push(generateMaintenanceWindow({
                start_time: startTime.toISOString(),
                duration: 2 // 2-hour duration ensures overlap
            }));
        }

        return windows;
    },

    // Test case: Long-running windows
    long_running_windows: () => {
        return generateMaintenanceWindows(5, {
            status: 'active',
            timeRange: { min: -86400000, max: 0 } // Started within last 24 hours
        }).map(window => ({
            ...window,
            duration: randomIntBetween(12, 72) // 12-72 hours
        }));
    }
};

/**
 * Generate load test dataset
 */
export function generateLoadTestData() {
    return {
        scenarios: generateMaintenanceScenarios(),
        testCases: {
            concurrent_endings: testCases.concurrent_endings(),
            overlapping_windows: testCases.overlapping_windows(),
            long_running_windows: testCases.long_running_windows()
        },
        extensions: Array.from({ length: 20 }, () => generateExtensionData()),
        completions: Array.from({ length: 20 }, () => generateCompletionData())
    };
}
