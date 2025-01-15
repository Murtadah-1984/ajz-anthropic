import { describe, expect } from 'k6/x/expect';
import {
    validateMaintenanceWindow,
    validateExtensionData,
    validateCompletionData,
    validateMaintenanceWindows,
    validateTestScenarios,
    validateLoadTestData
} from './validators.js';

export default function () {
    describe('Maintenance Window Validation', () => {
        // Valid window test
        {
            const validWindow = {
                environment: 'prod',
                start_time: new Date(Date.now() + 3600000).toISOString(),
                duration: 2,
                comment: 'Valid maintenance window',
                status: 'pending'
            };

            const result = validateMaintenanceWindow(validWindow);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Invalid environment
        {
            const invalidEnvWindow = {
                environment: 'invalid',
                start_time: new Date(Date.now() + 3600000).toISOString(),
                duration: 2,
                comment: 'Test window',
                status: 'pending'
            };

            const result = validateMaintenanceWindow(invalidEnvWindow);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Invalid environment: invalid');
        }

        // Invalid duration
        {
            const invalidDurationWindow = {
                environment: 'prod',
                start_time: new Date(Date.now() + 3600000).toISOString(),
                duration: 100,
                comment: 'Test window',
                status: 'pending'
            };

            const result = validateMaintenanceWindow(invalidDurationWindow);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Duration must be between 1 and 72 hours');
        }

        // Missing required fields
        {
            const incompleteWindow = {
                environment: 'prod',
                status: 'pending'
            };

            const result = validateMaintenanceWindow(incompleteWindow);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Missing required field: start_time');
            expect(result.errors).toContain('Missing required field: duration');
            expect(result.errors).toContain('Missing required field: comment');
        }
    });

    describe('Extension Data Validation', () => {
        // Valid extension
        {
            const validExtension = {
                duration: 2,
                reason: 'Valid extension reason with sufficient length'
            };

            const result = validateExtensionData(validExtension);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Invalid duration
        {
            const invalidDurationExtension = {
                duration: 25,
                reason: 'Valid reason'
            };

            const result = validateExtensionData(invalidDurationExtension);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Extension duration must be between 1 and 24 hours');
        }

        // Short reason
        {
            const shortReasonExtension = {
                duration: 2,
                reason: 'Short'
            };

            const result = validateExtensionData(shortReasonExtension);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Extension reason must be between 10 and 500 characters');
        }
    });

    describe('Completion Data Validation', () => {
        // Valid completion
        {
            const validCompletion = {
                completion_status: 'completed',
                reason: 'Valid completion reason with sufficient length'
            };

            const result = validateCompletionData(validCompletion);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Valid partial completion
        {
            const validPartialCompletion = {
                completion_status: 'partially_completed',
                reason: 'Valid completion reason',
                completion_notes: 'Detailed completion notes',
                remaining_tasks: [
                    {
                        description: 'Remaining task description',
                        priority: 'high',
                        estimated_duration: 2
                    }
                ],
                follow_up_required: true,
                follow_up_date: new Date(Date.now() + 86400000).toISOString()
            };

            const result = validateCompletionData(validPartialCompletion);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Invalid status
        {
            const invalidStatusCompletion = {
                completion_status: 'invalid',
                reason: 'Valid reason'
            };

            const result = validateCompletionData(invalidStatusCompletion);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Invalid completion status');
        }

        // Missing partial completion data
        {
            const incompletePartialCompletion = {
                completion_status: 'partially_completed',
                reason: 'Valid reason'
            };

            const result = validateCompletionData(incompletePartialCompletion);
            expect(result.isValid).toBe(false);
            expect(result.errors).toContain('Completion notes required for partially completed status');
            expect(result.errors).toContain('Remaining tasks required for partially completed status');
        }
    });

    describe('Batch Window Validation', () => {
        // Valid batch
        {
            const validBatch = [
                {
                    environment: 'prod',
                    start_time: new Date(Date.now() + 3600000).toISOString(),
                    duration: 2,
                    comment: 'First window',
                    status: 'pending'
                },
                {
                    environment: 'staging',
                    start_time: new Date(Date.now() + 3600000).toISOString(),
                    duration: 2,
                    comment: 'Second window',
                    status: 'pending'
                }
            ];

            const result = validateMaintenanceWindows(validBatch);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Time conflict detection
        {
            const conflictingBatch = [
                {
                    environment: 'prod',
                    start_time: new Date(Date.now() + 3600000).toISOString(),
                    duration: 2,
                    comment: 'First window',
                    status: 'pending'
                },
                {
                    environment: 'prod',
                    start_time: new Date(Date.now() + 3600000).toISOString(),
                    duration: 2,
                    comment: 'Conflicting window',
                    status: 'pending'
                }
            ];

            const result = validateMaintenanceWindows(conflictingBatch);
            expect(result.isValid).toBe(false);
            expect(result.errors[0]).toContain('Time conflict detected');
        }
    });

    describe('Test Scenario Validation', () => {
        // Valid scenarios
        {
            const validScenarios = {
                normal_operations: [
                    {
                        environment: 'prod',
                        start_time: new Date(Date.now() + 3600000).toISOString(),
                        duration: 2,
                        comment: 'Normal operation window',
                        status: 'pending'
                    }
                ],
                high_load_period: [
                    {
                        environment: 'prod',
                        start_time: new Date(Date.now() + 86400000).toISOString(),
                        duration: 2,
                        comment: 'High load window',
                        status: 'pending'
                    }
                ],
                concurrent_maintenance: [
                    {
                        environment: 'staging',
                        start_time: new Date(Date.now() + 3600000).toISOString(),
                        duration: 2,
                        comment: 'Concurrent window',
                        status: 'pending'
                    }
                ],
                mixed_environments: [
                    {
                        environment: 'dev',
                        start_time: new Date(Date.now() + 3600000).toISOString(),
                        duration: 2,
                        comment: 'Mixed environment window',
                        status: 'pending'
                    }
                ]
            };

            const result = validateTestScenarios(validScenarios);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }

        // Missing scenario
        {
            const invalidScenarios = {
                normal_operations: [
                    {
                        environment: 'prod',
                        start_time: new Date(Date.now() + 3600000).toISOString(),
                        duration: 2,
                        comment: 'Normal operation window',
                        status: 'pending'
                    }
                ]
                // Missing other required scenarios
            };

            const result = validateTestScenarios(invalidScenarios);
            expect(result.isValid).toBe(false);
        }
    });

    describe('Complete Dataset Validation', () => {
        // Valid dataset
        {
            const validDataset = {
                scenarios: {
                    normal_operations: [
                        {
                            environment: 'prod',
                            start_time: new Date(Date.now() + 3600000).toISOString(),
                            duration: 2,
                            comment: 'Normal operation window',
                            status: 'pending'
                        }
                    ],
                    high_load_period: [],
                    concurrent_maintenance: [],
                    mixed_environments: []
                },
                testCases: {
                    concurrent_endings: [
                        {
                            environment: 'prod',
                            start_time: new Date(Date.now() + 3600000).toISOString(),
                            duration: 2,
                            comment: 'Test case window',
                            status: 'pending'
                        }
                    ]
                },
                extensions: [
                    {
                        duration: 2,
                        reason: 'Valid extension reason'
                    }
                ],
                completions: [
                    {
                        completion_status: 'completed',
                        reason: 'Valid completion reason'
                    }
                ]
            };

            const result = validateLoadTestData(validDataset);
            expect(result.isValid).toBe(true);
            expect(result.errors).toEqual([]);
        }
    });
}
