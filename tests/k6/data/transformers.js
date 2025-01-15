/**
 * Data transformation utilities for maintenance window operations
 */

/**
 * Transform maintenance window to API format
 */
export function toApiFormat(window) {
    if (!window) return null;

    return {
        id: window.id,
        type: 'maintenance_window',
        attributes: {
            environment: window.environment,
            start_time: window.start_time,
            duration: window.duration,
            comment: window.comment,
            status: window.status,
            created_by: window.created_by
        },
        relationships: {
            tasks: {
                data: window.tasks?.map(task => ({
                    type: 'task',
                    id: task.id,
                    attributes: {
                        description: task.description,
                        priority: task.priority,
                        estimated_duration: task.estimated_duration
                    }
                }))
            }
        }
    };
}

/**
 * Transform maintenance window from API format
 */
export function fromApiFormat(data) {
    if (!data || data.type !== 'maintenance_window') return null;

    return {
        id: data.id,
        ...data.attributes,
        tasks: data.relationships?.tasks?.data?.map(task => ({
            id: task.id,
            ...task.attributes
        }))
    };
}

/**
 * Transform to monitoring format
 */
export function toMonitoringFormat(window) {
    if (!window) return null;

    const endTime = new Date(window.start_time);
    endTime.setHours(endTime.getHours() + window.duration);

    return {
        metric: 'maintenance_window',
        timestamp: new Date(window.start_time).getTime(),
        value: 1,
        tags: {
            environment: window.environment,
            status: window.status,
            duration_hours: window.duration.toString(),
            end_time: endTime.toISOString()
        },
        annotations: {
            comment: window.comment,
            created_by: window.created_by?.toString()
        }
    };
}

/**
 * Transform to calendar event format
 */
export function toCalendarEvent(window) {
    if (!window) return null;

    const startTime = new Date(window.start_time);
    const endTime = new Date(startTime.getTime() + window.duration * 3600000);

    return {
        title: `Maintenance Window - ${window.environment.toUpperCase()}`,
        start: startTime.toISOString(),
        end: endTime.toISOString(),
        allDay: false,
        description: window.comment,
        location: window.environment,
        status: window.status,
        metadata: {
            window_id: window.id,
            created_by: window.created_by
        }
    };
}

/**
 * Transform to notification format
 */
export function toNotificationFormat(window, type = 'created') {
    if (!window) return null;

    const baseNotification = {
        type: `maintenance_window.${type}`,
        timestamp: new Date().toISOString(),
        window_id: window.id,
        environment: window.environment,
        status: window.status
    };

    switch (type) {
        case 'created':
            return {
                ...baseNotification,
                title: 'New Maintenance Window Created',
                message: `A new maintenance window has been scheduled for ${window.environment}`,
                scheduled_for: window.start_time,
                duration: window.duration
            };

        case 'updated':
            return {
                ...baseNotification,
                title: 'Maintenance Window Updated',
                message: `Maintenance window for ${window.environment} has been updated`,
                changes: window.changes // Assuming changes are tracked
            };

        case 'started':
            return {
                ...baseNotification,
                title: 'Maintenance Window Started',
                message: `Maintenance window for ${window.environment} is now active`,
                expected_end_time: new Date(new Date(window.start_time).getTime() + window.duration * 3600000).toISOString()
            };

        case 'completed':
            return {
                ...baseNotification,
                title: 'Maintenance Window Completed',
                message: `Maintenance window for ${window.environment} has been completed`,
                completion_status: window.completion_status,
                actual_duration: window.actual_duration
            };

        default:
            return baseNotification;
    }
}

/**
 * Transform to report format
 */
export function toReportFormat(windows, options = {}) {
    const {
        groupBy = 'environment',
        timeRange = 'all',
        includeMetrics = true
    } = options;

    if (!Array.isArray(windows)) return null;

    // Filter by time range if specified
    let filteredWindows = windows;
    if (timeRange !== 'all') {
        const now = new Date();
        const timeRanges = {
            'day': 24 * 60 * 60 * 1000,
            'week': 7 * 24 * 60 * 60 * 1000,
            'month': 30 * 24 * 60 * 60 * 1000
        };

        const range = timeRanges[timeRange];
        if (range) {
            filteredWindows = windows.filter(w =>
                new Date(w.start_time).getTime() > now.getTime() - range
            );
        }
    }

    // Group windows
    const grouped = filteredWindows.reduce((acc, window) => {
        const key = groupBy === 'environment' ? window.environment :
                   groupBy === 'status' ? window.status :
                   groupBy === 'month' ? new Date(window.start_time).toISOString().substring(0, 7) :
                   'all';

        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(window);
        return acc;
    }, {});

    // Calculate metrics if requested
    const report = {
        summary: {
            total_windows: filteredWindows.length,
            time_range: timeRange,
            grouped_by: groupBy
        },
        groups: {}
    };

    for (const [key, items] of Object.entries(grouped)) {
        report.groups[key] = {
            windows: items,
            count: items.length
        };

        if (includeMetrics) {
            report.groups[key].metrics = {
                average_duration: items.reduce((sum, w) => sum + w.duration, 0) / items.length,
                completed_count: items.filter(w => w.status === 'completed').length,
                cancelled_count: items.filter(w => w.status === 'cancelled').length,
                partial_count: items.filter(w => w.status === 'partially_completed').length
            };
        }
    }

    return report;
}

/**
 * Transform to audit log format
 */
export function toAuditLogFormat(window, action, metadata = {}) {
    if (!window) return null;

    return {
        timestamp: new Date().toISOString(),
        entity_type: 'maintenance_window',
        entity_id: window.id,
        action,
        environment: window.environment,
        actor_id: metadata.actor_id,
        actor_type: metadata.actor_type || 'user',
        changes: metadata.changes || {},
        context: {
            window_status: window.status,
            window_environment: window.environment,
            ...metadata.context
        }
    };
}

/**
 * Transform batch of windows
 */
export function transformBatch(windows, format, options = {}) {
    if (!Array.isArray(windows)) return [];

    const transformers = {
        api: toApiFormat,
        monitoring: toMonitoringFormat,
        calendar: toCalendarEvent,
        notification: toNotificationFormat,
        report: (window) => toReportFormat([window], options),
        audit: (window) => toAuditLogFormat(window, options.action, options.metadata)
    };

    const transformer = transformers[format];
    if (!transformer) return windows;

    return windows
        .map(window => transformer(window))
        .filter(result => result !== null);
}
