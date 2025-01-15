const fs = require('fs');
const path = require('path');
const { ChartJSNodeCanvas } = require('chartjs-node-canvas');
const ValidationBenchmark = require('./benchmark');

/**
 * Benchmark visualization tools
 */
class BenchmarkVisualizer {
    constructor(outputDir = 'benchmark-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 800,
            height: 400,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Run benchmarks and generate visualizations
     */
    async visualize() {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        // Run benchmarks
        const benchmark = new ValidationBenchmark();
        const results = await this.runBenchmarks(benchmark);

        // Generate visualizations
        await Promise.all([
            this.generateExecutionTimeChart(results),
            this.generateScalingChart(results),
            this.generateComplexityChart(results),
            this.generateMemoryUsageChart(results),
            this.generateSummaryReport(results)
        ]);

        console.log(`\nVisualizations generated in ${this.outputDir}`);
    }

    /**
     * Run benchmarks and collect results
     */
    async runBenchmarks(benchmark) {
        console.log('Running benchmarks...');

        const results = {
            executionTimes: {},
            memoryUsage: {},
            timestamps: []
        };

        // Collect results during benchmark run
        benchmark.suite.on('cycle', event => {
            const { name, hz, stats } = event.target;
            const timestamp = new Date().toISOString();

            results.executionTimes[name] = 1000 / hz; // Convert to milliseconds
            results.memoryUsage[name] = process.memoryUsage().heapUsed / 1024 / 1024; // MB
            results.timestamps.push(timestamp);
        });

        await benchmark.run();
        return results;
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
                        text: 'Validation Execution Times'
                    }
                }
            }
        };

        await this.saveChart('execution-times.png', config);
    }

    /**
     * Generate scaling chart
     */
    async generateScalingChart(results) {
        const sizes = ['small', 'medium', 'large'];
        const data = {
            labels: sizes,
            datasets: [{
                label: 'Execution Time',
                data: sizes.map(size => results.executionTimes[`Validate ${size} Config`]),
                fill: false,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
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
                        text: 'Performance Scaling'
                    }
                }
            }
        };

        await this.saveChart('scaling.png', config);
    }

    /**
     * Generate complexity chart
     */
    async generateComplexityChart(results) {
        const complexityTests = [
            'Validate Default Config',
            'Validate Deeply Nested Config',
            'Validate With Many Rules'
        ];

        const data = {
            labels: complexityTests,
            datasets: [{
                label: 'Execution Time',
                data: complexityTests.map(test => results.executionTimes[test]),
                backgroundColor: [
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(255, 206, 86, 0.2)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
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
                        text: 'Complexity Analysis'
                    }
                }
            }
        };

        await this.saveChart('complexity.png', config);
    }

    /**
     * Generate memory usage chart
     */
    async generateMemoryUsageChart(results) {
        const data = {
            labels: Object.keys(results.memoryUsage),
            datasets: [{
                label: 'Memory Usage (MB)',
                data: Object.values(results.memoryUsage),
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
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
                            text: 'Memory (MB)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Memory Usage Analysis'
                    }
                }
            }
        };

        await this.saveChart('memory-usage.png', config);
    }

    /**
     * Generate summary report
     */
    async generateSummaryReport(results) {
        const report = {
            timestamp: new Date().toISOString(),
            executionTimes: this.calculateStatistics(Object.values(results.executionTimes)),
            memoryUsage: this.calculateStatistics(Object.values(results.memoryUsage)),
            scaling: this.analyzeScaling(results),
            recommendations: this.generateRecommendations(results)
        };

        const html = this.generateHtmlReport(report);
        fs.writeFileSync(path.join(this.outputDir, 'report.html'), html);
    }

    /**
     * Calculate statistics
     */
    calculateStatistics(values) {
        const sorted = [...values].sort((a, b) => a - b);
        return {
            min: sorted[0],
            max: sorted[sorted.length - 1],
            mean: values.reduce((a, b) => a + b) / values.length,
            median: sorted[Math.floor(sorted.length / 2)],
            p95: sorted[Math.floor(sorted.length * 0.95)],
            stdDev: Math.sqrt(
                values.reduce((sq, n) => sq + Math.pow(n - (values.reduce((a, b) => a + b) / values.length), 2), 0) /
                (values.length - 1)
            )
        };
    }

    /**
     * Analyze scaling characteristics
     */
    analyzeScaling(results) {
        const sizes = ['small', 'medium', 'large'];
        const times = sizes.map(size => results.executionTimes[`Validate ${size} Config`]);

        // Calculate scaling factor between sizes
        const factors = [];
        for (let i = 1; i < times.length; i++) {
            factors.push(times[i] / times[i - 1]);
        }

        return {
            factors,
            type: this.determineScalingType(factors)
        };
    }

    /**
     * Determine scaling type
     */
    determineScalingType(factors) {
        const avgFactor = factors.reduce((a, b) => a + b) / factors.length;

        if (avgFactor <= 1.2) return 'Linear (O(n))';
        if (avgFactor <= 2) return 'Log-linear (O(n log n))';
        if (avgFactor <= 4) return 'Quadratic (O(n²))';
        return 'Exponential (O(2ⁿ))';
    }

    /**
     * Generate recommendations
     */
    generateRecommendations(results) {
        const recommendations = [];
        const stats = this.calculateStatistics(Object.values(results.executionTimes));

        if (stats.mean > 100) {
            recommendations.push({
                type: 'performance',
                message: 'Consider implementing caching for validation results',
                priority: 'high'
            });
        }

        if (stats.stdDev > stats.mean * 0.5) {
            recommendations.push({
                type: 'optimization',
                message: 'High variance in execution times - consider batch processing',
                priority: 'medium'
            });
        }

        const scaling = this.analyzeScaling(results);
        if (scaling.type !== 'Linear (O(n))') {
            recommendations.push({
                type: 'scaling',
                message: `Non-linear scaling detected (${scaling.type}) - review algorithm complexity`,
                priority: 'high'
            });
        }

        return recommendations;
    }

    /**
     * Generate HTML report
     */
    generateHtmlReport(report) {
        return `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Benchmark Results</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .section { margin: 20px 0; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f5f5f5; }
                        .chart { margin: 20px 0; }
                        .recommendation { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
                        .recommendation.high { border-color: #ff4444; }
                        .recommendation.medium { border-color: #ffbb33; }
                        .recommendation.low { border-color: #00C851; }
                    </style>
                </head>
                <body>
                    <h1>Benchmark Results</h1>
                    <p>Generated at: ${report.timestamp}</p>

                    <div class="section">
                        <h2>Execution Time Analysis</h2>
                        <table>
                            <tr>
                                <th>Metric</th>
                                <th>Value (ms)</th>
                            </tr>
                            ${Object.entries(report.executionTimes)
                                .map(([key, value]) => `
                                    <tr>
                                        <td>${key}</td>
                                        <td>${value.toFixed(4)}</td>
                                    </tr>
                                `).join('')}
                        </table>
                    </div>

                    <div class="section">
                        <h2>Memory Usage Analysis</h2>
                        <table>
                            <tr>
                                <th>Metric</th>
                                <th>Value (MB)</th>
                            </tr>
                            ${Object.entries(report.memoryUsage)
                                .map(([key, value]) => `
                                    <tr>
                                        <td>${key}</td>
                                        <td>${value.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                        </table>
                    </div>

                    <div class="section">
                        <h2>Scaling Analysis</h2>
                        <p>Detected scaling type: ${report.scaling.type}</p>
                        <p>Scaling factors between sizes: ${report.scaling.factors
                            .map(f => f.toFixed(2))
                            .join(', ')}</p>
                    </div>

                    <div class="section">
                        <h2>Charts</h2>
                        <div class="chart">
                            <img src="execution-times.png" alt="Execution Times">
                        </div>
                        <div class="chart">
                            <img src="scaling.png" alt="Scaling Analysis">
                        </div>
                        <div class="chart">
                            <img src="complexity.png" alt="Complexity Analysis">
                        </div>
                        <div class="chart">
                            <img src="memory-usage.png" alt="Memory Usage">
                        </div>
                    </div>

                    <div class="section">
                        <h2>Recommendations</h2>
                        ${report.recommendations
                            .map(rec => `
                                <div class="recommendation ${rec.priority}">
                                    <strong>${rec.type}:</strong> ${rec.message}
                                    <br>
                                    <small>Priority: ${rec.priority}</small>
                                </div>
                            `).join('')}
                    </div>
                </body>
            </html>
        `;
    }

    /**
     * Save chart to file
     */
    async saveChart(filename, config) {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

// Run visualizer if called directly
if (require.main === module) {
    const visualizer = new BenchmarkVisualizer();
    visualizer.visualize().catch(console.error);
}

module.exports = BenchmarkVisualizer;
