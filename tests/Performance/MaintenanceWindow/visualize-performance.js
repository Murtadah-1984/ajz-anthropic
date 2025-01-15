const fs = require('fs');
const path = require('path');
const Chart = require('chart.js');
const { createCanvas } = require('canvas');

/**
 * Generate performance visualization charts
 */
class PerformanceVisualizer {
    constructor(logsDir) {
        this.logsDir = logsDir;
        this.outputDir = path.join(logsDir, 'charts');
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }
    }

    /**
     * Generate all performance charts
     */
    async generateCharts() {
        const files = fs.readdirSync(this.logsDir).filter(f => f.endsWith('.json'));

        for (const file of files) {
            const data = JSON.parse(fs.readFileSync(path.join(this.logsDir, file)));
            await this.generateChartsForTest(data);
        }

        await this.generateSummaryReport();
    }

    /**
     * Generate charts for a specific test
     */
    async generateChartsForTest(data) {
        const { test_name, results } = data;
        const baseFileName = test_name.toLowerCase().replace(/\s+/g, '_');

        // Execution Time Chart
        await this.generateExecutionTimeChart(results, baseFileName);

        // Memory Usage Chart
        await this.generateMemoryUsageChart(results, baseFileName);

        // Throughput Chart
        await this.generateThroughputChart(results, baseFileName);

        // If test has format-specific results, generate comparison charts
        if (this.hasFormatSpecificResults(results)) {
            await this.generateFormatComparisonChart(results, baseFileName);
        }

        // If test has concurrency results, generate scaling charts
        if (this.hasConcurrencyResults(results)) {
            await this.generateConcurrencyScalingChart(results, baseFileName);
        }
    }

    /**
     * Generate execution time chart
     */
    async generateExecutionTimeChart(results, baseFileName) {
        const canvas = createCanvas(800, 400);
        const ctx = canvas.getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(results).map(String),
                datasets: [{
                    label: 'Average Execution Time (ms)',
                    data: Object.values(results).map(r => r.avg_time_ms),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
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
                            text: 'Batch Size'
                        }
                    }
                }
            }
        });

        const buffer = canvas.toBuffer('image/png');
        fs.writeFileSync(path.join(this.outputDir, `${baseFileName}_execution_time.png`), buffer);
    }

    /**
     * Generate memory usage chart
     */
    async generateMemoryUsageChart(results, baseFileName) {
        const canvas = createCanvas(800, 400);
        const ctx = canvas.getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(results).map(String),
                datasets: [{
                    label: 'Memory Usage (MB)',
                    data: Object.values(results).map(r => r.memory_mb),
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgb(153, 102, 255)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Memory (MB)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Batch Size'
                        }
                    }
                }
            }
        });

        const buffer = canvas.toBuffer('image/png');
        fs.writeFileSync(path.join(this.outputDir, `${baseFileName}_memory_usage.png`), buffer);
    }

    /**
     * Generate throughput chart
     */
    async generateThroughputChart(results, baseFileName) {
        const canvas = createCanvas(800, 400);
        const ctx = canvas.getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(results).map(String),
                datasets: [{
                    label: 'Items Processed per Second',
                    data: Object.values(results).map(r => r.items_per_second),
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Items/Second'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Batch Size'
                        }
                    }
                }
            }
        });

        const buffer = canvas.toBuffer('image/png');
        fs.writeFileSync(path.join(this.outputDir, `${baseFileName}_throughput.png`), buffer);
    }

    /**
     * Generate format comparison chart
     */
    async generateFormatComparisonChart(results, baseFileName) {
        const canvas = createCanvas(800, 400);
        const ctx = canvas.getContext('2d');

        const formats = Object.keys(results[Object.keys(results)[0]]);
        const datasets = formats.map((format, index) => ({
            label: format,
            data: Object.values(results).map(r => r[format].avg_time_ms),
            borderColor: this.getColorForIndex(index),
            tension: 0.1
        }));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(results).map(String),
                datasets
            },
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
                            text: 'Batch Size'
                        }
                    }
                }
            }
        });

        const buffer = canvas.toBuffer('image/png');
        fs.writeFileSync(path.join(this.outputDir, `${baseFileName}_format_comparison.png`), buffer);
    }

    /**
     * Generate concurrency scaling chart
     */
    async generateConcurrencyScalingChart(results, baseFileName) {
        const canvas = createCanvas(800, 400);
        const ctx = canvas.getContext('2d');

        const batchSizes = Object.keys(results);
        const datasets = batchSizes.map((size, index) => ({
            label: `Batch Size ${size}`,
            data: Object.entries(results[size]).map(([concurrency, data]) => ({
                x: parseInt(concurrency),
                y: data.items_per_second
            })),
            borderColor: this.getColorForIndex(index),
            tension: 0.1
        }));

        new Chart(ctx, {
            type: 'line',
            data: { datasets },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Items/Second'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Concurrency Level'
                        },
                        type: 'linear',
                        position: 'bottom'
                    }
                }
            }
        });

        const buffer = canvas.toBuffer('image/png');
        fs.writeFileSync(path.join(this.outputDir, `${baseFileName}_concurrency_scaling.png`), buffer);
    }

    /**
     * Generate summary report
     */
    async generateSummaryReport() {
        const files = fs.readdirSync(this.logsDir).filter(f => f.endsWith('.json'));
        const summary = {
            timestamp: new Date().toISOString(),
            tests: {}
        };

        for (const file of files) {
            const data = JSON.parse(fs.readFileSync(path.join(this.logsDir, file)));
            summary.tests[data.test_name] = this.analyzePerfData(data.results);
        }

        fs.writeFileSync(
            path.join(this.outputDir, 'performance_summary.html'),
            this.generateHtmlReport(summary)
        );
    }

    /**
     * Analyze performance data
     */
    analyzePerfData(results) {
        const batchSizes = Object.keys(results).map(Number);
        const timeData = Object.values(results).map(r => r.avg_time_ms);
        const memoryData = Object.values(results).map(r => r.memory_mb);
        const throughputData = Object.values(results).map(r => r.items_per_second);

        return {
            execution_time: {
                min: Math.min(...timeData),
                max: Math.max(...timeData),
                avg: timeData.reduce((a, b) => a + b) / timeData.length
            },
            memory_usage: {
                min: Math.min(...memoryData),
                max: Math.max(...memoryData),
                avg: memoryData.reduce((a, b) => a + b) / memoryData.length
            },
            throughput: {
                min: Math.min(...throughputData),
                max: Math.max(...throughputData),
                avg: throughputData.reduce((a, b) => a + b) / throughputData.length
            },
            scaling: {
                time_complexity: this.calculateTimeComplexity(batchSizes, timeData),
                memory_complexity: this.calculateMemoryComplexity(batchSizes, memoryData)
            }
        };
    }

    /**
     * Calculate time complexity
     */
    calculateTimeComplexity(sizes, times) {
        const n = sizes.length;
        const x = sizes.map(s => Math.log(s));
        const y = times.map(t => Math.log(t));

        const sumX = x.reduce((a, b) => a + b);
        const sumY = y.reduce((a, b) => a + b);
        const sumXY = x.reduce((sum, xi, i) => sum + xi * y[i], 0);
        const sumXX = x.reduce((sum, xi) => sum + xi * xi, 0);

        const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);

        // Interpret the slope to determine complexity
        if (slope <= 1.1) return 'O(n)';
        if (slope <= 1.5) return 'O(n log n)';
        if (slope <= 2.1) return 'O(n²)';
        return 'O(n³) or worse';
    }

    /**
     * Calculate memory complexity
     */
    calculateMemoryComplexity(sizes, memory) {
        const n = sizes.length;
        const x = sizes;
        const y = memory;

        const sumX = x.reduce((a, b) => a + b);
        const sumY = y.reduce((a, b) => a + b);
        const sumXY = x.reduce((sum, xi, i) => sum + xi * y[i], 0);
        const sumXX = x.reduce((sum, xi) => sum + xi * xi, 0);

        const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);

        if (slope <= 0.1) return 'O(1)';
        if (slope <= 1.1) return 'O(n)';
        return 'O(n²) or worse';
    }

    /**
     * Generate HTML report
     */
    generateHtmlReport(summary) {
        return `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Performance Test Results</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; }
                        .metric { margin: 10px 0; }
                        .chart { margin: 20px 0; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                        th { background-color: #f5f5f5; }
                    </style>
                </head>
                <body>
                    <h1>Performance Test Results</h1>
                    <p>Generated at: ${summary.timestamp}</p>
                    ${Object.entries(summary.tests).map(([testName, data]) => `
                        <div class="test-section">
                            <h2>${testName}</h2>
                            <div class="metrics">
                                <h3>Execution Time</h3>
                                <table>
                                    <tr>
                                        <th>Minimum (ms)</th>
                                        <th>Maximum (ms)</th>
                                        <th>Average (ms)</th>
                                    </tr>
                                    <tr>
                                        <td>${data.execution_time.min.toFixed(2)}</td>
                                        <td>${data.execution_time.max.toFixed(2)}</td>
                                        <td>${data.execution_time.avg.toFixed(2)}</td>
                                    </tr>
                                </table>

                                <h3>Memory Usage</h3>
                                <table>
                                    <tr>
                                        <th>Minimum (MB)</th>
                                        <th>Maximum (MB)</th>
                                        <th>Average (MB)</th>
                                    </tr>
                                    <tr>
                                        <td>${data.memory_usage.min.toFixed(2)}</td>
                                        <td>${data.memory_usage.max.toFixed(2)}</td>
                                        <td>${data.memory_usage.avg.toFixed(2)}</td>
                                    </tr>
                                </table>

                                <h3>Throughput</h3>
                                <table>
                                    <tr>
                                        <th>Minimum (items/s)</th>
                                        <th>Maximum (items/s)</th>
                                        <th>Average (items/s)</th>
                                    </tr>
                                    <tr>
                                        <td>${data.throughput.min.toFixed(2)}</td>
                                        <td>${data.throughput.max.toFixed(2)}</td>
                                        <td>${data.throughput.avg.toFixed(2)}</td>
                                    </tr>
                                </table>

                                <h3>Complexity Analysis</h3>
                                <table>
                                    <tr>
                                        <th>Time Complexity</th>
                                        <th>Memory Complexity</th>
                                    </tr>
                                    <tr>
                                        <td>${data.scaling.time_complexity}</td>
                                        <td>${data.scaling.memory_complexity}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="charts">
                                <img src="${testName.toLowerCase().replace(/\s+/g, '_')}_execution_time.png" alt="Execution Time Chart">
                                <img src="${testName.toLowerCase().replace(/\s+/g, '_')}_memory_usage.png" alt="Memory Usage Chart">
                                <img src="${testName.toLowerCase().replace(/\s+/g, '_')}_throughput.png" alt="Throughput Chart">
                            </div>
                        </div>
                    `).join('')}
                </body>
            </html>
        `;
    }

    /**
     * Helper method to check if results have format-specific data
     */
    hasFormatSpecificResults(results) {
        const firstResult = results[Object.keys(results)[0]];
        return firstResult && typeof firstResult === 'object' &&
               Object.keys(firstResult).some(key => ['api', 'monitoring', 'calendar', 'notification'].includes(key));
    }

    /**
     * Helper method to check if results have concurrency data
     */
    hasConcurrencyResults(results) {
        const firstResult = results[Object.keys(results)[0]];
        return firstResult && typeof firstResult === 'object' &&
               Object.keys(firstResult).some(key => !isNaN(parseInt(key)));
    }

    /**
     * Get color for chart series
     */
    getColorForIndex(index) {
        const colors = [
            'rgb(75, 192, 192)',
            'rgb(255, 99, 132)',
            'rgb(153, 102, 255)',
            'rgb(255, 159, 64)',
            'rgb(54, 162, 235)'
        ];
        return colors[index % colors.length];
    }
}

// Run visualization if called directly
if (require.main === module) {
    const logsDir = process.argv[2] || path.join(__dirname, '../../../storage/logs');
    const visualizer = new PerformanceVisualizer(logsDir);
    visualizer.generateCharts().catch(console.error);
}

module.exports = PerformanceVisualizer;
