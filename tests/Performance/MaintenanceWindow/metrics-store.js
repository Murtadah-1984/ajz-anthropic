const fs = require('fs');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

/**
 * Performance metrics storage
 */
class MetricsStore {
    constructor(dbPath = 'metrics.db') {
        this.dbPath = dbPath;
        this.db = null;
        this.initialized = false;
    }

    /**
     * Initialize database
     */
    async initialize() {
        if (this.initialized) return;

        return new Promise((resolve, reject) => {
            this.db = new sqlite3.Database(this.dbPath, async (err) => {
                if (err) {
                    reject(err);
                    return;
                }

                try {
                    await this.createTables();
                    this.initialized = true;
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        });
    }

    /**
     * Create database tables
     */
    async createTables() {
        const queries = [
            `CREATE TABLE IF NOT EXISTS metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                metric_type TEXT NOT NULL,
                metric_name TEXT NOT NULL,
                value REAL NOT NULL,
                tags TEXT
            )`,
            `CREATE TABLE IF NOT EXISTS execution_times (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                operation TEXT NOT NULL,
                duration REAL NOT NULL,
                success BOOLEAN DEFAULT 1,
                error_message TEXT
            )`,
            `CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                metric_type TEXT NOT NULL,
                metric_name TEXT NOT NULL,
                threshold REAL NOT NULL,
                actual_value REAL NOT NULL,
                message TEXT
            )`,
            `CREATE INDEX IF NOT EXISTS idx_metrics_timestamp ON metrics(timestamp)`,
            `CREATE INDEX IF NOT EXISTS idx_metrics_type_name ON metrics(metric_type, metric_name)`,
            `CREATE INDEX IF NOT EXISTS idx_execution_times_timestamp ON execution_times(timestamp)`,
            `CREATE INDEX IF NOT EXISTS idx_alerts_timestamp ON alerts(timestamp)`
        ];

        for (const query of queries) {
            await this.run(query);
        }
    }

    /**
     * Store metric
     */
    async storeMetric(type, name, value, tags = {}) {
        await this.initialize();
        const query = `
            INSERT INTO metrics (metric_type, metric_name, value, tags)
            VALUES (?, ?, ?, ?)
        `;
        await this.run(query, [type, name, value, JSON.stringify(tags)]);
    }

    /**
     * Store execution time
     */
    async storeExecutionTime(operation, duration, success = true, errorMessage = null) {
        await this.initialize();
        const query = `
            INSERT INTO execution_times (operation, duration, success, error_message)
            VALUES (?, ?, ?, ?)
        `;
        await this.run(query, [operation, duration, success ? 1 : 0, errorMessage]);
    }

    /**
     * Store alert
     */
    async storeAlert(type, name, threshold, value, message = null) {
        await this.initialize();
        const query = `
            INSERT INTO alerts (metric_type, metric_name, threshold, actual_value, message)
            VALUES (?, ?, ?, ?, ?)
        `;
        await this.run(query, [type, name, threshold, value, message]);
    }

    /**
     * Get metrics by type
     */
    async getMetrics(type, name = null, timeRange = '24h') {
        await this.initialize();
        const timeConstraint = this.getTimeConstraint(timeRange);
        const params = [type];
        let query = `
            SELECT * FROM metrics
            WHERE metric_type = ?
            AND timestamp >= datetime('now', ?)
        `;

        if (name) {
            query += ' AND metric_name = ?';
            params.push(name);
        }

        query += ' ORDER BY timestamp ASC';
        params.unshift(timeConstraint);

        return await this.all(query, params);
    }

    /**
     * Get execution times
     */
    async getExecutionTimes(operation = null, timeRange = '24h') {
        await this.initialize();
        const timeConstraint = this.getTimeConstraint(timeRange);
        const params = [];
        let query = `
            SELECT * FROM execution_times
            WHERE timestamp >= datetime('now', ?)
        `;

        if (operation) {
            query += ' AND operation = ?';
            params.push(operation);
        }

        query += ' ORDER BY timestamp ASC';
        params.unshift(timeConstraint);

        return await this.all(query, params);
    }

    /**
     * Get alerts
     */
    async getAlerts(type = null, timeRange = '24h') {
        await this.initialize();
        const timeConstraint = this.getTimeConstraint(timeRange);
        const params = [];
        let query = `
            SELECT * FROM alerts
            WHERE timestamp >= datetime('now', ?)
        `;

        if (type) {
            query += ' AND metric_type = ?';
            params.push(type);
        }

        query += ' ORDER BY timestamp DESC';
        params.unshift(timeConstraint);

        return await this.all(query, params);
    }

    /**
     * Get metric statistics
     */
    async getMetricStats(type, name, timeRange = '24h') {
        await this.initialize();
        const timeConstraint = this.getTimeConstraint(timeRange);
        const query = `
            SELECT
                COUNT(*) as count,
                AVG(value) as average,
                MIN(value) as minimum,
                MAX(value) as maximum,
                (
                    SELECT value
                    FROM metrics
                    WHERE metric_type = ?
                    AND metric_name = ?
                    AND timestamp >= datetime('now', ?)
                    ORDER BY value
                    LIMIT 1
                    OFFSET (
                        SELECT COUNT(*)
                        FROM metrics
                        WHERE metric_type = ?
                        AND metric_name = ?
                        AND timestamp >= datetime('now', ?)
                    ) * 95 / 100
                ) as percentile_95
            FROM metrics
            WHERE metric_type = ?
            AND metric_name = ?
            AND timestamp >= datetime('now', ?)
        `;

        const params = [
            type, name, timeConstraint,
            type, name, timeConstraint,
            type, name, timeConstraint
        ];

        return await this.get(query, params);
    }

    /**
     * Get time series data
     */
    async getTimeSeries(type, name, interval = '1h', timeRange = '24h') {
        await this.initialize();
        const timeConstraint = this.getTimeConstraint(timeRange);
        const query = `
            SELECT
                strftime('%Y-%m-%d %H:%M', timestamp, 'localtime') as time_bucket,
                AVG(value) as average,
                MIN(value) as minimum,
                MAX(value) as maximum,
                COUNT(*) as count
            FROM metrics
            WHERE metric_type = ?
            AND metric_name = ?
            AND timestamp >= datetime('now', ?)
            GROUP BY time_bucket
            ORDER BY time_bucket ASC
        `;

        return await this.all(query, [type, name, timeConstraint]);
    }

    /**
     * Export data to JSON
     */
    async exportToJson(outputPath) {
        await this.initialize();
        const data = {
            metrics: await this.all('SELECT * FROM metrics ORDER BY timestamp ASC'),
            executionTimes: await this.all('SELECT * FROM execution_times ORDER BY timestamp ASC'),
            alerts: await this.all('SELECT * FROM alerts ORDER BY timestamp ASC')
        };

        fs.writeFileSync(outputPath, JSON.stringify(data, null, 2));
    }

    /**
     * Import data from JSON
     */
    async importFromJson(inputPath) {
        const data = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
        await this.initialize();

        await this.run('BEGIN TRANSACTION');

        try {
            for (const metric of data.metrics) {
                await this.storeMetric(
                    metric.metric_type,
                    metric.metric_name,
                    metric.value,
                    JSON.parse(metric.tags || '{}')
                );
            }

            for (const execution of data.executionTimes) {
                await this.storeExecutionTime(
                    execution.operation,
                    execution.duration,
                    execution.success,
                    execution.error_message
                );
            }

            for (const alert of data.alerts) {
                await this.storeAlert(
                    alert.metric_type,
                    alert.metric_name,
                    alert.threshold,
                    alert.actual_value,
                    alert.message
                );
            }

            await this.run('COMMIT');
        } catch (error) {
            await this.run('ROLLBACK');
            throw error;
        }
    }

    /**
     * Clean old data
     */
    async cleanup(retentionDays = 30) {
        await this.initialize();
        const queries = [
            'DELETE FROM metrics WHERE timestamp < datetime("now", ?)',
            'DELETE FROM execution_times WHERE timestamp < datetime("now", ?)',
            'DELETE FROM alerts WHERE timestamp < datetime("now", ?)',
            'VACUUM'
        ];

        const retention = `-${retentionDays} days`;
        for (const query of queries) {
            await this.run(query, [retention]);
        }
    }

    /**
     * Run SQL query
     */
    async run(sql, params = []) {
        return new Promise((resolve, reject) => {
            this.db.run(sql, params, function(err) {
                if (err) reject(err);
                else resolve(this);
            });
        });
    }

    /**
     * Get single row
     */
    async get(sql, params = []) {
        return new Promise((resolve, reject) => {
            this.db.get(sql, params, (err, row) => {
                if (err) reject(err);
                else resolve(row);
            });
        });
    }

    /**
     * Get multiple rows
     */
    async all(sql, params = []) {
        return new Promise((resolve, reject) => {
            this.db.all(sql, params, (err, rows) => {
                if (err) reject(err);
                else resolve(rows);
            });
        });
    }

    /**
     * Get time constraint
     */
    getTimeConstraint(timeRange) {
        const matches = timeRange.match(/^(\d+)([hdwmy])$/);
        if (!matches) throw new Error('Invalid time range format');

        const [_, value, unit] = matches;
        const units = {
            h: 'hours',
            d: 'days',
            w: 'weeks',
            m: 'months',
            y: 'years'
        };

        return `-${value} ${units[unit]}`;
    }

    /**
     * Close database connection
     */
    close() {
        if (this.db) {
            this.db.close();
            this.initialized = false;
        }
    }
}

module.exports = MetricsStore;
