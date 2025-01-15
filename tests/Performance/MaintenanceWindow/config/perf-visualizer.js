const fs = require('fs');
const path = require('path');
const { ChartJSNodeCanvas } = require('chartjs-node-canvas');

/**
 * Performance visualization utilities
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

        await Promise.all([
            this.generateExecutionTimeChart(results),
            this.generateMemoryUsageChart(results),
            this.generateConcurrencyChart(results),
            this.generateResourceUsageChart(results),
            this.generateSummaryReport(results)
        ]);
    }

    /**
     * Generate execution time chart
     */
    async generateExecutionTimeChart(results) {
        const data = {
            labels: results.validationTimes.map((_, i) => `Run ${i + 1}`),
            datasets: [{
                label: 'Validation Time (ms)',
                data: results.validationTimes,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
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
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Validation Execution Times'
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
            labels: results.memoryUsage.map((_, i) => `Run ${i + 1}`),
            datasets: [
                {
                    label: 'Heap Used (MB)',
                    data: results.memoryUsage.map(m => m.heapUsed / 1024 / 1024),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false
                },
                {
                    label: 'Heap Total (MB)',
                    data: results.memoryUsage.map(m => m.heapTotal / 1024 / 1024),
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
     * Generate concurrency chart
     */
    async generateConcurrencyChart(results) {
        const data = {
            labels: results.concurrencyResults.map(r => `${r.concurrent} Validations`),
            datasets: [
                {
                    label: 'Average Time (ms)',
                    data: results.concurrencyResults.map(r => r.avgDuration),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Max Time (ms)',
                    data: results.concurrencyResults.map(r => r.maxDuration),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
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
                        text: 'Performance Under Concurrency'
                    }
                }
            }
        };

        await this.saveChart('concurrency.png', config);
    }

    /**
     * Generate resource usage chart
     */
    async generateResourceUsageChart(results) {
        const data = {
            labels: results.resourceUsage.map((_, i) => `Run ${i + 1}`),
            datasets: [
                {
                    label: 'CPU Usage (%)',
                    data: results.resourceUsage.map(r => r.cpu),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    yAxisID: 'cpu'
                },
                {
                    label: 'Memory Usage (MB)',
                    data: results.resourceUsage.map(r => r.memory),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    yAxisID: 'memory'
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
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>Performance Test Results</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Summary</h2>
                            <div class="metric">
                                <span>Average Validation Time:</span>
                                <span>${results.summary.avgValidationTime.toFixed(2)}ms</span>
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
                            <h2>Execution Time Analysis</h2>
                            <div class="chart">
                                <img src="execution-times.png" alt="Execution Times">
                            </div>
                            <table>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                                <tr>
                                    <td>Minimum Time</td>
                                    <td>${results.summary.minTime.toFixed(2)}ms</td>
                                </tr>
                                <tr>
                                    <td>Maximum Time</td>
                                    <td>${results.summary.maxTime.toFixed(2)}ms</td>
                                </tr>
                                <tr>
                                    <td>95th Percentile</td>
                                    <td>${results.summary.p95Time.toFixed(2)}ms</td>
                                </tr>
                            </table>
                        </div>

                        <div class="section">
                            <h2>Memory Usage Analysis</h2>
                            <div class="chart">
                                <img src="memory-usage.png" alt="Memory Usage">
                            </div>
                            <table>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                                <tr>
                                    <td>Initial Memory</td>
                                    <td>${results.summary.initialMemory.toFixed(2)}MB</td>
                                </tr>
                                <tr>
                                    <td>Peak Memory</td>
                                    <td>${results.summary.peakMemory.toFixed(2)}MB</td>
                                </tr>
                                <tr>
                                    <td>Memory Growth</td>
                                    <td>${results.summary.memoryGrowth.toFixed(2)}MB</td>
                                </tr>
                            </table>
                        </div>

                        <div class="section">
                            <h2>Concurrency Analysis</h2>
                            <div class="chart">
                                <img src="concurrency.png" alt="Concurrency Performance">
                            </div>
                            <table>
                                <tr>
                                    <th>Concurrent Validations</th>
                                    <th>Average Time (ms)</th>
                                    <th>Max Time (ms)</th>
                                </tr>
                                ${results.concurrencyResults.map(r => `
                                    <tr>
                                        <td>${r.concurrent}</td>
                                        <td>${r.avgDuration.toFixed(2)}</td>
                                        <td>${r.maxDuration.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </table>
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
                                </tr>
                                <tr>
                                    <td>CPU Usage</td>
                                    <td>${results.summary.avgCpuUsage.toFixed(2)}%</td>
                                    <td>${results.summary.peakCpuUsage.toFixed(2)}%</td>
                                </tr>
                                <tr>
                                    <td>Memory Usage</td>
                                    <td>${results.summary.avgMemory.toFixed(2)}MB</td>
                                    <td>${results.summary.peakMemory.toFixed(2)}MB</td>
                                </tr>
                            </table>
                        </div>

                        ${results.warnings.length > 0 ? `
                            <div class="section">
                                <h2>Performance Warnings</h2>
                                ${results.warnings.map(warning => `
                                    <div class="warning">
                                        <strong>${warning.type}:</strong> ${warning.message}
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
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
