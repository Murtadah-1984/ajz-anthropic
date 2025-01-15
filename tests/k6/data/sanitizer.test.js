import { describe, expect } from 'k6/x/expect';
import {
    sanitizeMaintenanceWindow,
    sanitizeExtensionData,
    sanitizeCompletionData,
    sanitizeMaintenanceWindows,
    sanitizeLoadTestData
} from './sanitizers.js';

export default function () {
    describe('Maintenance Window Sanitization', () => {
        // Valid window test
        {
            const validWindow = {
                environment: ' PROD ',
                start_time: '2024-01-01T12:00:00Z',
                duration: '2',
                comment: '  Valid maintenance window  ',
                status: ' PENDING ',
                created_by: '123'
            };

            const result = sanitizeMaintenanceWindow(validWindow);
            expect(result).not.toBe(null);
            expect(result.environment).toBe('prod');
            expect(result.duration).toBe(2);
            expect(result.status).toBe('pending');
            expect(result.created_by).toBe(123);
        }

        // Invalid input types
        {
            expect(sanitizeMaintenanceWindow(null)).toBe(null);
            expect(sanitizeMaintenanceWindow(undefined)).toBe(null);
            expect(sanitizeMaintenanceWindow('not an object')).toBe(null);
            expect(sanitizeMaintenanceWindow(123)).toBe(null);
        }

        // Invalid environment
        {
            const invalidEnvWindow = {
                environment: 'invalid',
                start_time: '2024-01-01T12:00:00Z',
                duration: 2,
                comment: 'Test window',
                status: 'pending'
            };

            const result = sanitizeMaintenanceWindow(invalidEnvWindow);
            expect(result.environment).toBe(null);
        }

        // Invalid datetime
        {
            const invalidDateWindow = {
                environment: 'prod',
                start_time: 'not a date',
                duration: 2,
                comment: 'Test window',
                status: 'pending'
            };

            const result = sanitizeMaintenanceWindow(invalidDateWindow);
            expect(result.start_time).toBe(null);
        }

        // Comment sanitization
        {
            const dirtyCommentWindow = {
                environment: 'prod',
                start_time: '2024-01-01T12:00:00Z',
                duration: 2,
                comment: '  <script>alert("xss")</script>  Multiple    spaces   ',
                status: 'pending'
            };

            const result = sanitizeMaintenanceWindow(dirtyCommentWindow);
            expect(result.comment).not.toContain('<script>');
            expect(result.comment).not.toContain('  '); // No double spaces
        }
    });

    describe('Extension Data Sanitization', () => {
        // Valid extension
        {
            const validExtension = {
                duration: '4',
                reason: '  Valid extension reason  '
            };

            const result = sanitizeExtensionData(validExtension);
            expect(result).not.toBe(null);
            expect(result.duration).toBe(4);
            expect(result.reason).toBe('Valid extension reason');
        }

        // Invalid duration
        {
            const invalidDurationExt = {
                duration: '25', // Over 24 hour limit
                reason: 'Valid reason'
            };

            const result = sanitizeExtensionData(invalidDurationExt);
            expect(result.duration).toBe(null);
        }

        // Short reason
        {
            const shortReasonExt = {
                duration: 2,
                reason: 'Short'
            };

            const result = sanitizeExtensionData(shortReasonExt);
            expect(result.reason).toBe(null);
        }
    });

    describe('Completion Data Sanitization', () => {
        // Valid completion
        {
            const validCompletion = {
                completion_status: ' COMPLETED ',
                reason: 'Valid completion reason'
            };

            const result = sanitizeCompletionData(validCompletion);
            expect(result).not.toBe(null);
            expect(result.completion_status).toBe('completed');
        }

        // Valid partial completion
        {
            const validPartialCompletion = {
                completion_status: 'partially_completed',
                reason: 'Valid reason',
                completion_notes: 'Detailed completion notes',
                remaining_tasks: [
                    {
                        description: 'Task description',
                        priority: ' HIGH ',
                        estimated_duration: '2'
                    }
                ],
                follow_up_required: true,
                follow_up_date: '2024-01-01T12:00:00Z'
            };

            const result = sanitizeCompletionData(validPartialCompletion);
            expect(result).not.toBe(null);
            expect(result.completion_status).toBe('partially_completed');
            expect(result.remaining_tasks).toHaveLength(1);
            expect(result.remaining_tasks[0].priority).toBe('high');
            expect(result.remaining_tasks[0].estimated_duration).toBe(2);
        }

        // Invalid remaining tasks
        {
            const invalidTasksCompletion = {
                completion_status: 'partially_completed',
                reason: 'Valid reason',
                completion_notes: 'Notes',
                remaining_tasks: [
                    {
                        description: 'ok', // Too short
                        priority: 'invalid',
                        estimated_duration: -1
                    }
                ]
            };

            const result = sanitizeCompletionData(invalidTasksCompletion);
            expect(result.remaining_tasks).toBe(null);
        }
    });

    describe('Batch Window Sanitization', () => {
        // Valid batch
        {
            const validBatch = [
                {
                    environment: 'prod',
                    start_time: '2024-01-01T12:00:00Z',
                    duration: 2,
                    comment: 'First window',
                    status: 'pending'
                },
                {
                    environment: 'staging',
                    start_time: '2024-01-01T14:00:00Z',
                    duration: 2,
                    comment: 'Second window',
                    status: 'pending'
                }
            ];

            const result = sanitizeMaintenanceWindows(validBatch);
            expect(result).toHaveLength(2);
            expect(result[0].environment).toBe('prod');
            expect(result[1].environment).toBe('staging');
        }

        // Invalid items in batch
        {
            const mixedBatch = [
                {
                    environment: 'prod',
                    start_time: '2024-01-01T12:00:00Z',
                    duration: 2,
                    comment: 'Valid window',
                    status: 'pending'
                },
                null,
                'not a window',
                {
                    environment: 'invalid',
                    status: 'pending'
                }
            ];

            const result = sanitizeMaintenanceWindows(mixedBatch);
            expect(result).toHaveLength(1);
            expect(result[0].environment).toBe('prod');
        }
    });

    describe('Complete Dataset Sanitization', () => {
        // Valid dataset
        {
            const validDataset = {
                scenarios: {
                    normal_operations: [
                        {
                            environment: 'prod',
                            start_time: '2024-01-01T12:00:00Z',
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
                            start_time: '2024-01-01T12:00:00Z',
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

            const result = sanitizeLoadTestData(validDataset);
            expect(result).not.toBe(null);
            expect(result.scenarios.normal_operations).toHaveLength(1);
            expect(result.testCases.concurrent_endings).toHaveLength(1);
            expect(result.extensions).toHaveLength(1);
            expect(result.completions).toHaveLength(1);
        }

        // Invalid dataset structure
        {
            const invalidDataset = {
                scenarios: 'not an object',
                testCases: null,
                extensions: 'not an array',
                completions: 123
            };

            const result = sanitizeLoadTestData(invalidDataset);
            expect(result.scenarios.normal_operations).toHaveLength(0);
            expect(Object.keys(result.testCases)).toHaveLength(0);
            expect(result.extensions).toHaveLength(0);
            expect(result.completions).toHaveLength(0);
        }
    });
}
