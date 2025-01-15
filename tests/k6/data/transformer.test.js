import { describe, expect } from 'k6/x/expect';
import {
    toApiFormat,
    fromApiFormat,
    toMonitoringFormat,
    toCalendarEvent,
    toNotificationFormat,
    toReportFormat,
    toAuditLogFormat,
    transformBatch
} from './transformers.js';

export default function () {
    const sampleWindow = {
        id: 1,
        environment: 'prod',
        start_time: '2024-01-01T12:00:00Z',
        duration: 2,
        comment: 'Test maintenance window',
        status: 'pending',
        created_by: 123,
        tasks: [
            {
                id: 1,
                description: 'System update',
                priority: 'high',
                estimated_duration: 1
            }
        ]
    };

    describe('API Format Transformation', () => {
        // To API format
        {
            const result = toApiFormat(sampleWindow);
            expect(result).not.toBe(null);
            expect(result.type).toBe('maintenance_window');
            expect(result.attributes.environment).toBe('prod');
            expect(result.relationships.tasks.data).toHaveLength(1);
            expect(result.relationships.tasks.data[0].type).toBe('task');
        }

        // From API format
        {
            const apiData = {
                id: 1,
                type: 'maintenance_window',
                attributes: {
                    environment: 'prod',
                    start_time: '2024-01-01T12:00:00Z',
                    duration: 2,
                    comment: 'Test window',
                    status: 'pending'
                },
                relationships: {
                    tasks: {
                        data: [
                            {
                                type: 'task',
                                id: 1,
                                attributes: {
                                    description: 'Task 1',
                                    priority: 'high',
                                    estimated_duration: 1
                                }
                            }
                        ]
                    }
                }
            };

            const result = fromApiFormat(apiData);
            expect(result).not.toBe(null);
            expect(result.environment).toBe('prod');
            expect(result.tasks).toHaveLength(1);
            expect(result.tasks[0].description).toBe('Task 1');
        }

        // Invalid input
        {
            expect(toApiFormat(null)).toBe(null);
            expect(fromApiFormat({ type: 'wrong_type' })).toBe(null);
        }
    });

    describe('Monitoring Format Transformation', () => {
        // Valid transformation
        {
            const result = toMonitoringFormat(sampleWindow);
            expect(result).not.toBe(null);
            expect(result.metric).toBe('maintenance_window');
            expect(result.tags.environment).toBe('prod');
            expect(result.tags.duration_hours).toBe('2');
            expect(result.annotations.created_by).toBe('123');
        }

        // Timestamp calculation
        {
            const result = toMonitoringFormat(sampleWindow);
            const expectedTimestamp = new Date('2024-01-01T12:00:00Z').getTime();
            expect(result.timestamp).toBe(expectedTimestamp);
        }
    });

    describe('Calendar Event Transformation', () => {
        // Valid transformation
        {
            const result = toCalendarEvent(sampleWindow);
            expect(result).not.toBe(null);
            expect(result.title).toContain('PROD');
            expect(result.start).toBe('2024-01-01T12:00:00Z');
            expect(result.allDay).toBe(false);
        }

        // Duration calculation
        {
            const result = toCalendarEvent(sampleWindow);
            const expectedEnd = new Date('2024-01-01T14:00:00Z').toISOString(); // 2 hours later
            expect(result.end).toBe(expectedEnd);
        }
    });

    describe('Notification Format Transformation', () => {
        // Created notification
        {
            const result = toNotificationFormat(sampleWindow, 'created');
            expect(result).not.toBe(null);
            expect(result.type).toBe('maintenance_window.created');
            expect(result.title).toBe('New Maintenance Window Created');
        }

        // Started notification
        {
            const result = toNotificationFormat(sampleWindow, 'started');
            expect(result).not.toBe(null);
            expect(result.type).toBe('maintenance_window.started');
            expect(result.expected_end_time).toBeDefined();
        }

        // Completed notification
        {
            const completedWindow = {
                ...sampleWindow,
                status: 'completed',
                completion_status: 'completed',
                actual_duration: 1.5
            };

            const result = toNotificationFormat(completedWindow, 'completed');
            expect(result).not.toBe(null);
            expect(result.completion_status).toBe('completed');
            expect(result.actual_duration).toBe(1.5);
        }
    });

    describe('Report Format Transformation', () => {
        const windows = [
            {
                ...sampleWindow,
                environment: 'prod',
                status: 'completed'
            },
            {
                ...sampleWindow,
                id: 2,
                environment: 'staging',
                status: 'pending'
            },
            {
                ...sampleWindow,
                id: 3,
                environment: 'prod',
                status: 'cancelled'
            }
        ];

        // Group by environment
        {
            const result = toReportFormat(windows, { groupBy: 'environment' });
            expect(result).not.toBe(null);
            expect(result.groups.prod.count).toBe(2);
            expect(result.groups.staging.count).toBe(1);
        }

        // Group by status
        {
            const result = toReportFormat(windows, { groupBy: 'status' });
            expect(result.groups.completed.count).toBe(1);
            expect(result.groups.pending.count).toBe(1);
            expect(result.groups.cancelled.count).toBe(1);
        }

        // Time range filtering
        {
            const futureWindow = {
                ...sampleWindow,
                start_time: new Date(Date.now() + 86400000).toISOString() // Tomorrow
            };

            const result = toReportFormat([futureWindow], { timeRange: 'day' });
            expect(result.summary.total_windows).toBe(1);
        }

        // Metrics calculation
        {
            const result = toReportFormat(windows, { includeMetrics: true });
            expect(result.groups.prod.metrics).toBeDefined();
            expect(result.groups.prod.metrics.average_duration).toBe(2);
            expect(result.groups.prod.metrics.completed_count).toBe(1);
            expect(result.groups.prod.metrics.cancelled_count).toBe(1);
        }
    });

    describe('Audit Log Format Transformation', () => {
        // Create action
        {
            const result = toAuditLogFormat(sampleWindow, 'create', {
                actor_id: 456,
                actor_type: 'admin'
            });

            expect(result).not.toBe(null);
            expect(result.action).toBe('create');
            expect(result.actor_id).toBe(456);
            expect(result.actor_type).toBe('admin');
        }

        // Update action with changes
        {
            const result = toAuditLogFormat(sampleWindow, 'update', {
                actor_id: 456,
                changes: {
                    duration: {
                        from: 2,
                        to: 4
                    }
                }
            });

            expect(result.changes.duration).toBeDefined();
            expect(result.changes.duration.from).toBe(2);
            expect(result.changes.duration.to).toBe(4);
        }
    });

    describe('Batch Transformation', () => {
        const windows = [sampleWindow, { ...sampleWindow, id: 2 }];

        // API format batch
        {
            const result = transformBatch(windows, 'api');
            expect(result).toHaveLength(2);
            expect(result[0].type).toBe('maintenance_window');
            expect(result[1].type).toBe('maintenance_window');
        }

        // Calendar format batch
        {
            const result = transformBatch(windows, 'calendar');
            expect(result).toHaveLength(2);
            expect(result[0].title).toContain('PROD');
            expect(result[1].title).toContain('PROD');
        }

        // Invalid format
        {
            const result = transformBatch(windows, 'invalid_format');
            expect(result).toEqual(windows);
        }

        // Empty input
        {
            expect(transformBatch(null, 'api')).toEqual([]);
            expect(transformBatch(undefined, 'api')).toEqual([]);
            expect(transformBatch([], 'api')).toEqual([]);
        }
    });
}
