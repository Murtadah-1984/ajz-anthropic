const fs = require('fs');
const path = require('path');
const { ChartJSNodeCanvas } = require('chartjs-node-canvas');

/**
 * Performance visualization tools
 */
class PerfVisualizer {
    constructor(outputDir = 'performance-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 800,
            height: 400,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Generate performance visualizations
     */
    async visualize(results) {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        // Generate charts
        await Promise.all([
            this.generateExecutionTimeChart(results),
            this.generateMemoryUsageChart(results),
            this.generateScalingChart(results),
            this.generateResourceUsageChart(results),
            this.generateSummaryReport(results)
        ]);

        console.log(`\nPerformance visualizations generated in ${this.outputDir}`);
    }

    /**
     * Generate execution time chart
     */
    async generateExecutionTimeChart(results) {
        const data = {
            labels: Object.keys(results.executionTimes),
            datasets: [{
                label: 'Execution Time (ms)',
                data: Object.values(results.executionTimes),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };

        const config = {
            type: 'bar',
            data,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Time (ms)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Export Operation Execution Times'
                    }
                }
            }
        };

        await this.saveChart('execution-times.png', config);
    }

    /**
     * Generate memory usage chart
     */
    async generateMemoryUsageChart(results) {
        const data = {
            labels: results.memorySnapshots.map(s => s.label),
            datasets: [
                {
                    label: 'Heap Used (MB)',
                    data: results.memorySnapshots.map(s => s.heapUsed),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false
                },
                {
                    label: 'Heap Total (MB)',
                    data: results.memorySnapshots.map(s => s.heapTotal),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false
                }
            ]
        };

        const config = {
            type: 'line',
            data,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Memory (MB)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Memory Usage Over Time'
                    }
                }
            }
        };

        await this.saveChart('memory-usage.png', config);
    }

    /**
     * Generate scaling chart
     */
    async generateScalingChart(results) {
        const data = {
            labels: results.scaling.sizes,
            datasets: [
                {
                    label: 'Actual Time',
                    data: results.scaling.times,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false
                },
                {
                    label: 'Linear Projection',
                    data: results.scaling.linearProjection,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderDash: [5, 5],
                    fill: false
                }
            ]
        };

        const config = {
            type: 'line',
            data,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Time (ms)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Number of Records'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Performance Scaling Analysis'
                    }
                }
            }
        };

        await this.saveChart('scaling.png', config);
    }

    /**
     * Generate resource usage chart
     */
    async generateResourceUsageChart(results) {
        const data = {
            labels: results.resourceUsage.timestamps,
            datasets: [
                {
                    label: 'CPU Usage (%)',
                    data: results.resourceUsage.cpu,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    yAxisID: 'cpu'
                },
                {
                    label: 'Memory Usage (MB)',
                    data: results.resourceUsage.memory,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    yAxisID: 'memory'
                },
                {
                    label: 'Disk I/O (KB/s)',
                    data: results.resourceUsage.diskIO,
                    borderColor: 'rgba(153, 102, 255, 1)',
                    yAxisID: 'io'
                }
            ]
        };

        const config = {
            type: 'line',
            data,
            options: {
                scales: {
                    cpu: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'CPU Usage (%)'
                        }
                    },
                    memory: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Memory Usage (MB)'
                        }
                    },
                    io: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Disk I/O (KB/s)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Resource Usage Over Time'
                    }
                }
            }
        };

        await this.saveChart('resource-usage.png', config);
    }

    /**
     * Generate summary report
     */
    async generateSummaryReport(results) {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Performance Test Results</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            background-color: #f5f5f5;
                        }
                        .container {
                            max-width: 1200px;
                            margin: 0 auto;
                            background-color: white;
                            padding: 20px;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        }
                        .section {
                            margin: 20px 0;
                            padding: 20px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                        }
                        .chart {
                            margin: 20px 0;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        th, td {
                            padding: 8px;
                            border: 1px solid #ddd;
                            text-align: left;
                        }
                        th {
                            background-color: #f5f5f5;
                        }
                        .metric {
                            display: flex;
                            justify-content: space-between;
                            margin: 10px 0;
                            padding: 10px;
                            background-color: #f8f9fa;
                            border-radius: 4px;
                        }
                        .warning {
                            color: #856404;
                            background-color: #fff3cd;
                            border: 1px solid #ffeeba;
                            padding: 10px;
                            margin: 10px 0;
                            border-radius: 4px;
                        }
                        .recommendation {
                            color: #004085;
                            background-color: #cce5ff;
                            border: 1px solid #b8daff;
                            padding: 10px;
                            margin: 10px 0;
                            border-radius: 4px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>Performance Test Results</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Summary</h2>
                            <div class="metric">
                                <span>Average Execution Time:</span>
                                <span>${results.summary.avgExecutionTime.toFixed(2)}ms</span>
                            </div>
                            <div class="metric">
                                <span>Peak Memory Usage:</span>
                                <span>${results.summary.peakMemory.toFixed(2)}MB</span>
                            </div>
                            <div class="metric">
                                <span>Average CPU Usage:</span>
                                <span>${results.summary.avgCpuUsage.toFixed(2)}%</span>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Scaling Analysis</h2>
                            <div class="chart">
                                <img src="scaling.png" alt="Scaling Analysis">
                            </div>
                            <p>Scaling Factor: ${results.summary.scalingFactor.toFixed(2)}</p>
                            <p>Scaling Type: ${results.summary.scalingType}</p>
                        </div>

                        <div class="section">
                            <h2>Resource Usage</h2>
                            <div class="chart">
                                <img src="resource-usage.png" alt="Resource Usage">
                            </div>
                            <table>
                                <tr>
                                    <th>Metric</th>
                                    <th>Average</th>
                                    <th>Peak</th>
                                    <th>Threshold</th>
                                </tr>
                                <tr>
                                    <td>CPU Usage</td>
                                    <td>${results.summary.avgCpuUsage.toFixed(2)}%</td>
                                    <td>${results.summary.peakCpuUsage.toFixed(2)}%</td>
                                    <td>80%</td>
                                </tr>
                                <tr>
                                    <td>Memory Usage</td>
                                    <td>${results.summary.avgMemory.toFixed(2)}MB</td>
                                    <td>${results.summary.peakMemory.toFixed(2)}MB</td>
                                    <td>${results.thresholds.memory}MB</td>
                                </tr>
                                <tr>
                                    <td>Disk I/O</td>
                                    <td>${results.summary.avgDiskIO.toFixed(2)}KB/s</td>
                                    <td>${results.summary.peakDiskIO.toFixed(2)}KB/s</td>
                                    <td>${results.thresholds.diskIO}KB/s</td>
                                </tr>
                            </table>
                        </div>

                        <div class="section">
                            <h2>Format-Specific Performance</h2>
                            <div class="chart">
                                <img src="execution-times.png" alt="Execution Times">
                            </div>
                            <table>
                                <tr>
                                    <th>Format</th>
                                    <th>Average Time (ms)</th>
                                    <th>Memory Impact (MB)</th>
                                    <th>File Size (KB)</th>
                                </tr>
                                ${Object.entries(results.formatMetrics)
                                    .map(([format, metrics]) => `
                                        <tr>
                                            <td>${format}</td>
                                            <td>${metrics.avgTime.toFixed(2)}</td>
                                            <td>${metrics.memoryImpact.toFixed(2)}</td>
                                            <td>${metrics.fileSize.toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                            </table>
                        </div>

                        <div class="section">
                            <h2>Warnings</h2>
                            ${results.warnings.map(warning => `
                                <div class="warning">
                                    <strong>${warning.type}:</strong> ${warning.message}
                                </div>
                            `).join('')}
                        </div>

                        <div class="section">
                            <h2>Recommendations</h2>
                            ${results.recommendations.map(rec => `
                                <div class="recommendation">
                                    <strong>${rec.type}:</strong> ${rec.message}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'report.html'), html);
    }

    /**
     * Save chart to file
     */
    async saveChart(filename, config) {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

module.exports = PerfVisualizer;
