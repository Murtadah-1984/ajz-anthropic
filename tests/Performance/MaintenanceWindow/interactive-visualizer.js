const fs = require('fs');
const path = require('path');
const express = require('express');
const open = require('open');

/**
 * Interactive performance visualization server
 */
class InteractiveVisualizer {
    constructor(port = 3000) {
        this.port = port;
        this.app = express();
        this.setupServer();
    }

    /**
     * Setup express server
     */
    setupServer() {
        this.app.use(express.static(path.join(__dirname, 'public')));
        this.app.use(express.json());

        // API endpoints
        this.app.get('/api/results', (req, res) => {
            res.json(this.results);
        });

        this.app.get('/api/trends', (req, res) => {
            res.json(this.calculateTrends());
        });
    }

    /**
     * Generate interactive visualizations
     */
    async visualize(results) {
        this.results = results;
        this.generateHtml();
        await this.startServer();
    }

    /**
     * Generate interactive HTML
     */
    generateHtml() {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Interactive Performance Results</title>
                    <script src="https://d3js.org/d3.v7.min.js"></script>
                    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            background-color: #f5f5f5;
                        }
                        .container {
                            max-width: 1400px;
                            margin: 0 auto;
                            display: grid;
                            grid-template-columns: repeat(2, 1fr);
                            gap: 20px;
                        }
                        .chart-container {
                            background: white;
                            padding: 20px;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        }
                        .full-width {
                            grid-column: 1 / -1;
                        }
                        .controls {
                            margin: 10px 0;
                            padding: 10px;
                            background: #f8f9fa;
                            border-radius: 4px;
                        }
                        .tooltip {
                            position: absolute;
                            padding: 8px;
                            background: rgba(0,0,0,0.8);
                            color: white;
                            border-radius: 4px;
                            pointer-events: none;
                        }
                        .metric {
                            font-size: 24px;
                            font-weight: bold;
                            text-align: center;
                            padding: 20px;
                        }
                        .metric-label {
                            font-size: 14px;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="chart-container full-width">
                            <h2>Performance Overview</h2>
                            <div class="controls">
                                <label>
                                    Time Range:
                                    <select id="timeRange">
                                        <option value="1h">Last Hour</option>
                                        <option value="24h">Last 24 Hours</option>
                                        <option value="7d">Last 7 Days</option>
                                        <option value="30d">Last 30 Days</option>
                                    </select>
                                </label>
                                <label>
                                    Metrics:
                                    <select id="metrics" multiple>
                                        <option value="executionTime">Execution Time</option>
                                        <option value="memory">Memory Usage</option>
                                        <option value="cpu">CPU Usage</option>
                                        <option value="diskIO">Disk I/O</option>
                                    </select>
                                </label>
                            </div>
                            <div id="overviewChart"></div>
                        </div>

                        <div class="chart-container">
                            <h2>Memory Analysis</h2>
                            <div class="controls">
                                <label>
                                    View:
                                    <select id="memoryView">
                                        <option value="timeline">Timeline</option>
                                        <option value="histogram">Histogram</option>
                                        <option value="boxplot">Box Plot</option>
                                    </select>
                                </label>
                            </div>
                            <div id="memoryChart"></div>
                        </div>

                        <div class="chart-container">
                            <h2>CPU Analysis</h2>
                            <div class="controls">
                                <label>
                                    Aggregation:
                                    <select id="cpuAggregation">
                                        <option value="avg">Average</option>
                                        <option value="max">Maximum</option>
                                        <option value="p95">95th Percentile</option>
                                    </select>
                                </label>
                            </div>
                            <div id="cpuChart"></div>
                        </div>

                        <div class="chart-container full-width">
                            <h2>Format Comparison</h2>
                            <div class="controls">
                                <label>
                                    Metric:
                                    <select id="formatMetric">
                                        <option value="time">Execution Time</option>
                                        <option value="memory">Memory Impact</option>
                                        <option value="size">File Size</option>
                                    </select>
                                </label>
                            </div>
                            <div id="formatChart"></div>
                        </div>

                        <div class="chart-container">
                            <h2>Resource Correlation</h2>
                            <div class="controls">
                                <label>
                                    X Axis:
                                    <select id="correlationX">
                                        <option value="executionTime">Execution Time</option>
                                        <option value="memory">Memory Usage</option>
                                        <option value="cpu">CPU Usage</option>
                                    </select>
                                </label>
                                <label>
                                    Y Axis:
                                    <select id="correlationY">
                                        <option value="memory">Memory Usage</option>
                                        <option value="executionTime">Execution Time</option>
                                        <option value="cpu">CPU Usage</option>
                                    </select>
                                </label>
                            </div>
                            <div id="correlationChart"></div>
                        </div>

                        <div class="chart-container">
                            <h2>Trend Analysis</h2>
                            <div class="controls">
                                <label>
                                    Metric:
                                    <select id="trendMetric">
                                        <option value="executionTime">Execution Time</option>
                                        <option value="memory">Memory Usage</option>
                                        <option value="cpu">CPU Usage</option>
                                    </select>
                                </label>
                            </div>
                            <div id="trendChart"></div>
                        </div>
                    </div>

                    <script>
                        // Load data and initialize charts
                        async function initialize() {
                            const response = await fetch('/api/results');
                            const results = await response.json();

                            createOverviewChart(results);
                            createMemoryChart(results);
                            createCpuChart(results);
                            createFormatChart(results);
                            createCorrelationChart(results);
                            createTrendChart(results);

                            // Setup event listeners
                            setupEventListeners(results);
                        }

                        // Create overview chart
                        function createOverviewChart(results) {
                            const data = [{
                                x: results.resourceUsage.timestamps,
                                y: results.resourceUsage.cpu,
                                type: 'scatter',
                                name: 'CPU Usage'
                            }, {
                                x: results.resourceUsage.timestamps,
                                y: results.resourceUsage.memory,
                                type: 'scatter',
                                name: 'Memory Usage',
                                yaxis: 'y2'
                            }];

                            const layout = {
                                title: 'Resource Usage Over Time',
                                xaxis: { title: 'Time' },
                                yaxis: { title: 'CPU Usage (%)' },
                                yaxis2: {
                                    title: 'Memory Usage (MB)',
                                    overlaying: 'y',
                                    side: 'right'
                                }
                            };

                            Plotly.newPlot('overviewChart', data, layout);
                        }

                        // Create memory chart
                        function createMemoryChart(results) {
                            const trace = {
                                y: results.memorySnapshots.map(s => s.heapUsed),
                                type: 'box',
                                name: 'Memory Distribution'
                            };

                            const layout = {
                                title: 'Memory Usage Distribution',
                                yaxis: { title: 'Memory Usage (MB)' }
                            };

                            Plotly.newPlot('memoryChart', [trace], layout);
                        }

                        // Create CPU chart
                        function createCpuChart(results) {
                            const trace = {
                                x: results.resourceUsage.timestamps,
                                y: results.resourceUsage.cpu,
                                type: 'scatter',
                                mode: 'lines+markers',
                                name: 'CPU Usage'
                            };

                            const layout = {
                                title: 'CPU Usage Over Time',
                                xaxis: { title: 'Time' },
                                yaxis: { title: 'CPU Usage (%)' }
                            };

                            Plotly.newPlot('cpuChart', [trace], layout);
                        }

                        // Create format comparison chart
                        function createFormatChart(results) {
                            const data = Object.entries(results.formatMetrics).map(([format, metrics]) => ({
                                type: 'bar',
                                name: format,
                                x: ['Execution Time', 'Memory Impact', 'File Size'],
                                y: [metrics.avgTime, metrics.memoryImpact, metrics.fileSize]
                            }));

                            const layout = {
                                title: 'Format Comparison',
                                barmode: 'group'
                            };

                            Plotly.newPlot('formatChart', data, layout);
                        }

                        // Create correlation chart
                        function createCorrelationChart(results) {
                            const trace = {
                                x: results.resourceUsage.cpu,
                                y: results.resourceUsage.memory,
                                mode: 'markers',
                                type: 'scatter',
                                marker: {
                                    size: 10,
                                    color: results.resourceUsage.timestamps,
                                    colorscale: 'Viridis',
                                    showscale: true
                                }
                            };

                            const layout = {
                                title: 'CPU vs Memory Usage',
                                xaxis: { title: 'CPU Usage (%)' },
                                yaxis: { title: 'Memory Usage (MB)' }
                            };

                            Plotly.newPlot('correlationChart', [trace], layout);
                        }

                        // Create trend chart
                        function createTrendChart(results) {
                            const trace = {
                                y: results.resourceUsage.cpu,
                                type: 'scatter',
                                mode: 'lines',
                                name: 'Actual',
                                line: { color: 'blue' }
                            };

                            const trendline = {
                                y: calculateTrendline(results.resourceUsage.cpu),
                                type: 'scatter',
                                mode: 'lines',
                                name: 'Trend',
                                line: { color: 'red', dash: 'dot' }
                            };

                            const layout = {
                                title: 'CPU Usage Trend',
                                showlegend: true
                            };

                            Plotly.newPlot('trendChart', [trace, trendline], layout);
                        }

                        // Calculate trendline
                        function calculateTrendline(data) {
                            const n = data.length;
                            const sum_x = data.reduce((a, _, i) => a + i, 0);
                            const sum_y = data.reduce((a, b) => a + b, 0);
                            const sum_xy = data.reduce((a, b, i) => a + b * i, 0);
                            const sum_xx = data.reduce((a, _, i) => a + i * i, 0);

                            const slope = (n * sum_xy - sum_x * sum_y) / (n * sum_xx - sum_x * sum_x);
                            const intercept = (sum_y - slope * sum_x) / n;

                            return data.map((_, i) => slope * i + intercept);
                        }

                        // Setup event listeners
                        function setupEventListeners(results) {
                            // Time range change
                            document.getElementById('timeRange').addEventListener('change', (e) => {
                                const range = e.target.value;
                                updateTimeRange(range, results);
                            });

                            // Memory view change
                            document.getElementById('memoryView').addEventListener('change', (e) => {
                                const view = e.target.value;
                                updateMemoryView(view, results);
                            });

                            // CPU aggregation change
                            document.getElementById('cpuAggregation').addEventListener('change', (e) => {
                                const aggregation = e.target.value;
                                updateCpuAggregation(aggregation, results);
                            });

                            // Format metric change
                            document.getElementById('formatMetric').addEventListener('change', (e) => {
                                const metric = e.target.value;
                                updateFormatMetric(metric, results);
                            });

                            // Correlation axes change
                            document.getElementById('correlationX').addEventListener('change', (e) => {
                                updateCorrelation(results);
                            });
                            document.getElementById('correlationY').addEventListener('change', (e) => {
                                updateCorrelation(results);
                            });

                            // Trend metric change
                            document.getElementById('trendMetric').addEventListener('change', (e) => {
                                const metric = e.target.value;
                                updateTrendMetric(metric, results);
                            });
                        }

                        // Initialize on load
                        initialize();
                    </script>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(__dirname, 'public', 'index.html'), html);
    }

    /**
     * Calculate performance trends
     */
    calculateTrends() {
        const trends = {
            executionTime: this.calculateMetricTrend(this.results.executionTimes),
            memory: this.calculateMetricTrend(this.results.memorySnapshots.map(s => s.heapUsed)),
            cpu: this.calculateMetricTrend(this.results.resourceUsage.cpu)
        };

        return trends;
    }

    /**
     * Calculate trend for a specific metric
     */
    calculateMetricTrend(data) {
        const n = data.length;
        if (n < 2) return { slope: 0, correlation: 0 };

        const x = Array.from({ length: n }, (_, i) => i);
        const sum_x = x.reduce((a, b) => a + b, 0);
        const sum_y = data.reduce((a, b) => a + b, 0);
        const sum_xy = x.reduce((a, _, i) => a + data[i] * i, 0);
        const sum_xx = x.reduce((a, b) => a + b * b, 0);

        const slope = (n * sum_xy - sum_x * sum_y) / (n * sum_xx - sum_x * sum_x);
        const correlation = this.calculateCorrelation(x, data);

        return {
            slope,
            correlation,
            prediction: x.map(i => slope * i + (sum_y - slope * sum_x) / n)
        };
    }

    /**
     * Calculate correlation coefficient
     */
    calculateCorrelation(x, y) {
        const n = x.length;
        const sum_x = x.reduce((a, b) => a + b, 0);
        const sum_y = y.reduce((a, b) => a + b, 0);
        const sum_xy = x.reduce((a, _, i) => a + x[i] * y[i], 0);
        const sum_xx = x.reduce((a, b) => a + b * b, 0);
        const sum_yy = y.reduce((a, b) => a + b * b, 0);

        return (n * sum_xy - sum_x * sum_y) /
            Math.sqrt((n * sum_xx - sum_x * sum_x) * (n * sum_yy - sum_y * sum_y));
    }

    /**
     * Start express server
     */
    async startServer() {
        return new Promise((resolve) => {
            const server = this.app.listen(this.port, () => {
                console.log(`Interactive visualizations available at http://localhost:${this.port}`);
                open(`http://localhost:${this.port}`);
                resolve(server);
            });
        });
    }
}

module.exports = InteractiveVisualizer;
