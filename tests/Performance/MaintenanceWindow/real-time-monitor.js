const WebSocket = require('ws');
const EventEmitter = require('events');

/**
 * Real-time performance monitoring
 */
class RealTimeMonitor extends EventEmitter {
    constructor(port = 3001) {
        super();
        this.port = port;
        this.clients = new Set();
        this.metrics = {
            executionTimes: [],
            memoryUsage: [],
            cpuUsage: [],
            diskIO: [],
            timestamps: []
        };
        this.setupWebSocket();
    }

    /**
     * Setup WebSocket server
     */
    setupWebSocket() {
        this.wss = new WebSocket.Server({ port: this.port });

        this.wss.on('connection', (ws) => {
            this.clients.add(ws);
            console.log(`Client connected. Total clients: ${this.clients.size}`);

            // Send initial data
            ws.send(JSON.stringify({
                type: 'initial',
                data: this.metrics
            }));

            ws.on('close', () => {
                this.clients.delete(ws);
                console.log(`Client disconnected. Total clients: ${this.clients.size}`);
            });
        });

        console.log(`WebSocket server running on ws://localhost:${this.port}`);
    }

    /**
     * Start monitoring
     */
    start(interval = 1000) {
        this.interval = setInterval(() => this.collectMetrics(), interval);
        console.log(`Real-time monitoring started with ${interval}ms interval`);
    }

    /**
     * Stop monitoring
     */
    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            console.log('Real-time monitoring stopped');
        }
    }

    /**
     * Collect performance metrics
     */
    collectMetrics() {
        const metrics = {
            timestamp: new Date().toISOString(),
            memory: process.memoryUsage(),
            cpu: process.cpuUsage()
        };

        // Update metrics history
        this.updateMetrics(metrics);

        // Broadcast to all clients
        this.broadcast({
            type: 'update',
            data: metrics
        });

        // Emit metrics event
        this.emit('metrics', metrics);
    }

    /**
     * Update metrics history
     */
    updateMetrics(metrics) {
        const MAX_HISTORY = 100; // Keep last 100 data points

        this.metrics.timestamps.push(metrics.timestamp);
        this.metrics.memoryUsage.push(metrics.memory.heapUsed / 1024 / 1024); // MB
        this.metrics.cpuUsage.push(
            (metrics.cpu.user + metrics.cpu.system) / 1000000 // seconds
        );

        // Trim history if needed
        if (this.metrics.timestamps.length > MAX_HISTORY) {
            this.metrics.timestamps.shift();
            this.metrics.memoryUsage.shift();
            this.metrics.cpuUsage.shift();
        }
    }

    /**
     * Broadcast message to all clients
     */
    broadcast(message) {
        const data = JSON.stringify(message);
        this.clients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(data);
            }
        });
    }

    /**
     * Add execution time measurement
     */
    addExecutionTime(operation, time) {
        const metric = {
            timestamp: new Date().toISOString(),
            operation,
            time
        };

        this.metrics.executionTimes.push(metric);

        // Keep only recent history
        if (this.metrics.executionTimes.length > 100) {
            this.metrics.executionTimes.shift();
        }

        // Broadcast update
        this.broadcast({
            type: 'executionTime',
            data: metric
        });
    }

    /**
     * Add custom metric
     */
    addMetric(name, value) {
        const metric = {
            timestamp: new Date().toISOString(),
            name,
            value
        };

        if (!this.metrics[name]) {
            this.metrics[name] = [];
        }

        this.metrics[name].push(metric);

        // Keep only recent history
        if (this.metrics[name].length > 100) {
            this.metrics[name].shift();
        }

        // Broadcast update
        this.broadcast({
            type: 'customMetric',
            data: metric
        });
    }

    /**
     * Get current metrics
     */
    getMetrics() {
        return {
            ...this.metrics,
            summary: this.calculateSummary()
        };
    }

    /**
     * Calculate metrics summary
     */
    calculateSummary() {
        return {
            memory: {
                current: this.metrics.memoryUsage[this.metrics.memoryUsage.length - 1],
                average: this.average(this.metrics.memoryUsage),
                peak: Math.max(...this.metrics.memoryUsage)
            },
            cpu: {
                current: this.metrics.cpuUsage[this.metrics.cpuUsage.length - 1],
                average: this.average(this.metrics.cpuUsage),
                peak: Math.max(...this.metrics.cpuUsage)
            },
            executionTimes: {
                count: this.metrics.executionTimes.length,
                average: this.average(this.metrics.executionTimes.map(m => m.time)),
                p95: this.percentile(this.metrics.executionTimes.map(m => m.time), 95)
            }
        };
    }

    /**
     * Calculate average
     */
    average(arr) {
        return arr.reduce((a, b) => a + b, 0) / arr.length;
    }

    /**
     * Calculate percentile
     */
    percentile(arr, p) {
        const sorted = [...arr].sort((a, b) => a - b);
        const pos = (sorted.length - 1) * p / 100;
        const base = Math.floor(pos);
        const rest = pos - base;

        if (sorted[base + 1] !== undefined) {
            return sorted[base] + rest * (sorted[base + 1] - sorted[base]);
        } else {
            return sorted[base];
        }
    }

    /**
     * Add alert condition
     */
    addAlert(metric, threshold, callback) {
        this.on('metrics', (metrics) => {
            let value;
            switch (metric) {
                case 'memory':
                    value = metrics.memory.heapUsed / 1024 / 1024;
                    break;
                case 'cpu':
                    value = (metrics.cpu.user + metrics.cpu.system) / 1000000;
                    break;
                default:
                    if (this.metrics[metric]) {
                        value = this.metrics[metric][this.metrics[metric].length - 1];
                    }
            }

            if (value !== undefined && value > threshold) {
                callback({
                    metric,
                    value,
                    threshold,
                    timestamp: new Date().toISOString()
                });
            }
        });
    }
}

module.exports = RealTimeMonitor;
