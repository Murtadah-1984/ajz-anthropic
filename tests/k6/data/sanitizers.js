/**
 * Data sanitization utilities for maintenance window operations
 */

/**
 * Sanitize a single maintenance window
 */
export function sanitizeMaintenanceWindow(window) {
    if (!window || typeof window !== 'object') {
        return null;
    }

    return {
        environment: sanitizeEnvironment(window.environment),
        start_time: sanitizeDateTime(window.start_time),
        duration: sanitizeDuration(window.duration),
        comment: sanitizeComment(window.comment),
        status: sanitizeStatus(window.status),
        created_by: window.created_by ? parseInt(window.created_by, 10) : null
    };
}

/**
 * Sanitize environment value
 */
function sanitizeEnvironment(env) {
    if (!env || typeof env !== 'string') {
        return null;
    }

    const normalized = env.toLowerCase().trim();
    const validEnvironments = ['prod', 'staging', 'dev', 'test'];

    return validEnvironments.includes(normalized) ? normalized : null;
}

/**
 * Sanitize datetime string
 */
function sanitizeDateTime(datetime) {
    if (!datetime) {
        return null;
    }

    try {
        const date = new Date(datetime);
        if (isNaN(date.getTime())) {
            return null;
        }
        return date.toISOString();
    } catch (e) {
        return null;
    }
}

/**
 * Sanitize duration value
 */
function sanitizeDuration(duration) {
    if (duration === null || duration === undefined) {
        return null;
    }

    const num = parseInt(duration, 10);
    if (isNaN(num) || num < 1 || num > 72) {
        return null;
    }

    return num;
}

/**
 * Sanitize comment text
 */
function sanitizeComment(comment) {
    if (!comment || typeof comment !== 'string') {
        return null;
    }

    // Remove excessive whitespace
    let sanitized = comment.trim().replace(/\s+/g, ' ');

    // Remove potentially harmful characters
    sanitized = sanitized.replace(/[<>]/g, '');

    // Truncate if too long
    if (sanitized.length > 500) {
        sanitized = sanitized.substring(0, 497) + '...';
    }

    return sanitized.length >= 10 ? sanitized : null;
}

/**
 * Sanitize status value
 */
function sanitizeStatus(status) {
    if (!status || typeof status !== 'string') {
        return null;
    }

    const normalized = status.toLowerCase().trim();
    const validStatuses = ['pending', 'active', 'completed', 'cancelled', 'partially_completed'];

    return validStatuses.includes(normalized) ? normalized : null;
}

/**
 * Sanitize extension data
 */
export function sanitizeExtensionData(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    return {
        duration: sanitizeDuration(data.duration),
        reason: sanitizeComment(data.reason)
    };
}

/**
 * Sanitize completion data
 */
export function sanitizeCompletionData(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    const sanitized = {
        completion_status: sanitizeCompletionStatus(data.completion_status),
        reason: sanitizeComment(data.reason)
    };

    if (sanitized.completion_status === 'partially_completed') {
        sanitized.completion_notes = sanitizeComment(data.completion_notes);
        sanitized.remaining_tasks = sanitizeRemainingTasks(data.remaining_tasks);

        if (data.follow_up_required) {
            sanitized.follow_up_required = true;
            sanitized.follow_up_date = sanitizeDateTime(data.follow_up_date);
        }
    }

    return sanitized;
}

/**
 * Sanitize completion status
 */
function sanitizeCompletionStatus(status) {
    if (!status || typeof status !== 'string') {
        return null;
    }

    const normalized = status.toLowerCase().trim();
    const validStatuses = ['completed', 'partially_completed', 'cancelled'];

    return validStatuses.includes(normalized) ? normalized : null;
}

/**
 * Sanitize remaining tasks array
 */
function sanitizeRemainingTasks(tasks) {
    if (!Array.isArray(tasks)) {
        return null;
    }

    const sanitizedTasks = tasks
        .map(task => sanitizeTask(task))
        .filter(task => task !== null);

    return sanitizedTasks.length > 0 ? sanitizedTasks : null;
}

/**
 * Sanitize a single task
 */
function sanitizeTask(task) {
    if (!task || typeof task !== 'object') {
        return null;
    }

    const description = sanitizeTaskDescription(task.description);
    const priority = sanitizeTaskPriority(task.priority);
    const estimatedDuration = sanitizeTaskDuration(task.estimated_duration);

    if (!description || !priority) {
        return null;
    }

    return {
        description,
        priority,
        estimated_duration: estimatedDuration
    };
}

/**
 * Sanitize task description
 */
function sanitizeTaskDescription(description) {
    if (!description || typeof description !== 'string') {
        return null;
    }

    const sanitized = description.trim().replace(/\s+/g, ' ');
    return sanitized.length >= 5 ? sanitized : null;
}

/**
 * Sanitize task priority
 */
function sanitizeTaskPriority(priority) {
    if (!priority || typeof priority !== 'string') {
        return null;
    }

    const normalized = priority.toLowerCase().trim();
    const validPriorities = ['high', 'medium', 'low'];

    return validPriorities.includes(normalized) ? normalized : null;
}

/**
 * Sanitize task duration
 */
function sanitizeTaskDuration(duration) {
    if (duration === null || duration === undefined) {
        return null;
    }

    const num = parseInt(duration, 10);
    return !isNaN(num) && num > 0 ? num : null;
}

/**
 * Sanitize a batch of maintenance windows
 */
export function sanitizeMaintenanceWindows(windows) {
    if (!Array.isArray(windows)) {
        return [];
    }

    return windows
        .map(window => sanitizeMaintenanceWindow(window))
        .filter(window => window !== null);
}

/**
 * Sanitize complete test dataset
 */
export function sanitizeLoadTestData(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    return {
        scenarios: {
            normal_operations: sanitizeMaintenanceWindows(data.scenarios?.normal_operations),
            high_load_period: sanitizeMaintenanceWindows(data.scenarios?.high_load_period),
            concurrent_maintenance: sanitizeMaintenanceWindows(data.scenarios?.concurrent_maintenance),
            mixed_environments: sanitizeMaintenanceWindows(data.scenarios?.mixed_environments)
        },
        testCases: Object.entries(data.testCases || {}).reduce((acc, [key, value]) => {
            acc[key] = sanitizeMaintenanceWindows(value);
            return acc;
        }, {}),
        extensions: (data.extensions || [])
            .map(ext => sanitizeExtensionData(ext))
            .filter(ext => ext !== null),
        completions: (data.completions || [])
            .map(comp => sanitizeCompletionData(comp))
            .filter(comp => comp !== null)
    };
}
