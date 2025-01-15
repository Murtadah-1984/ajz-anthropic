const { ChartJSNodeCanvas } = require('chartjs-node-canvas');
const fs = require('fs');
const path = require('path');

/**
 * Comparative visualization utilities
 */
class ComparativeVisualizer {
    constructor(outputDir = 'comparative-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 1000,
            height: 500,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Generate comparative visualizations
     */
    async visualizeComparison(evaluations, labels) {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        await Promise.all([
            this.generateAccuracyComparison(evaluations, labels),
            this.generateMetricsComparison(evaluations, labels),
            this.generateStabilityComparison(evaluations, labels),
            this.generateReliabilityComparison(evaluations, labels),
            this.generatePerformanceComparison(evaluations, labels),
            this.generateComparativeSummary(evaluations, labels)
        ]);
    }

    /**
     * Generate accuracy comparison
     */
    async generateAccuracyComparison(evaluations, labels) {
        // Overall accuracy comparison
        const accuracyData = {
            labels,
            datasets: [{
                label: 'Overall Accuracy',
                data: evaluations.map(e => e.accuracy.overall.score),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };

        const accuracyConfig = {
            type: 'bar',
            data: accuracyData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Accuracy Score'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Accuracy Comparison'
                    }
                }
            }
        };

        await this.saveChart('accuracy-comparison.png', accuracyConfig);

        // Error metrics comparison
        const errorData = {
            labels,
            datasets: [
                {
                    label: 'MSE',
                    data: evaluations.map(e => e.accuracy.overall.mse),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'RMSE',
                    data: evaluations.map(e => e.accuracy.overall.rmse),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        };

        const errorConfig = {
            type: 'bar',
            data: errorData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Error Value'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Error Metrics Comparison'
                    }
                }
            }
        };

        await this.saveChart('error-comparison.png', errorConfig);
    }

    /**
     * Generate metrics comparison
     */
    async generateMetricsComparison(evaluations, labels) {
        const metrics = Object.keys(evaluations[0].metrics);
        const datasets = metrics.map((metric, i) => ({
            label: metric,
            data: evaluations.map(e => e.metrics[metric]),
            backgroundColor: `rgba(${75 + i * 50}, ${192 - i * 20}, ${192}, 0.2)`,
            borderColor: `rgba(${75 + i * 50}, ${192 - i * 20}, ${192}, 1)`,
            borderWidth: 1
        }));

        const metricsData = {
            labels,
            datasets
        };

        const metricsConfig = {
            type: 'radar',
            data: metricsData,
            options: {
                scales: {
                    r: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Metrics Comparison'
                    }
                }
            }
        };

        await this.saveChart('metrics-comparison.png', metricsConfig);
    }

    /**
     * Generate stability comparison
     */
    async generateStabilityComparison(evaluations, labels) {
        const stabilityData = {
            labels,
            datasets: [
                {
                    label: 'Variability',
                    data: evaluations.map(e => e.stability.variability.mean),
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Consistency',
                    data: evaluations.map(e => e.stability.consistency.meanCorrelation),
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }
            ]
        };

        const stabilityConfig = {
            type: 'bar',
            data: stabilityData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Score'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Stability Comparison'
                    }
                }
            }
        };

        await this.saveChart('stability-comparison.png', stabilityConfig);
    }

    /**
     * Generate reliability comparison
     */
    async generateReliabilityComparison(evaluations, labels) {
        const reliabilityData = {
            labels,
            datasets: [
                {
                    label: 'Reliability Score',
                    data: evaluations.map(e => e.reliability.score),
                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Confidence Score',
                    data: evaluations.map(e => e.reliability.confidence),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        };

        const reliabilityConfig = {
            type: 'line',
            data: reliabilityData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Score'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Reliability Comparison'
                    }
                }
            }
        };

        await this.saveChart('reliability-comparison.png', reliabilityConfig);
    }

    /**
     * Generate performance comparison
     */
    async generatePerformanceComparison(evaluations, labels) {
        // Training time comparison
        const timeData = {
            labels,
            datasets: [{
                label: 'Training Time (s)',
                data: evaluations.map(e => e.performance.trainingTime),
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        };

        const timeConfig = {
            type: 'bar',
            data: timeData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Time (seconds)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Training Time Comparison'
                    }
                }
            }
        };

        await this.saveChart('training-time-comparison.png', timeConfig);

        // Resource usage comparison
        const resourceData = {
            labels,
            datasets: [
                {
                    label: 'Memory Usage (MB)',
                    data: evaluations.map(e => e.performance.memoryUsage),
                    yAxisID: 'memory',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'CPU Usage (%)',
                    data: evaluations.map(e => e.performance.cpuUsage),
                    yAxisID: 'cpu',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        };

        const resourceConfig = {
            type: 'bar',
            data: resourceData,
            options: {
                scales: {
                    memory: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Memory (MB)'
                        }
                    },
                    cpu: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'CPU (%)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Resource Usage Comparison'
                    }
                }
            }
        };

        await this.saveChart('resource-usage-comparison.png', resourceConfig);
    }

    /**
     * Generate comparative summary
     */
    async generateComparativeSummary(evaluations, labels) {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Model Comparison Results</title>
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
                        .highlight {
                            background-color: #e8f5e9;
                        }
                        .improvement {
                            color: #4caf50;
                        }
                        .regression {
                            color: #f44336;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>Model Comparison Results</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Accuracy Comparison</h2>
                            <div class="chart">
                                <img src="accuracy-comparison.png" alt="Accuracy Comparison">
                            </div>
                            <div class="chart">
                                <img src="error-comparison.png" alt="Error Comparison">
                            </div>
                            <table>
                                <tr>
                                    <th>Model</th>
                                    <th>Accuracy</th>
                                    <th>MSE</th>
                                    <th>RMSE</th>
                                </tr>
                                ${evaluations.map((e, i) => `
                                    <tr>
                                        <td>${labels[i]}</td>
                                        <td>${e.accuracy.overall.score.toFixed(4)}</td>
                                        <td>${e.accuracy.overall.mse.toFixed(4)}</td>
                                        <td>${e.accuracy.overall.rmse.toFixed(4)}</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>

                        <div class="section">
                            <h2>Stability & Reliability</h2>
                            <div class="chart">
                                <img src="stability-comparison.png" alt="Stability Comparison">
                            </div>
                            <div class="chart">
                                <img src="reliability-comparison.png" alt="Reliability Comparison">
                            </div>
                            <table>
                                <tr>
                                    <th>Model</th>
                                    <th>Stability Score</th>
                                    <th>Reliability Score</th>
                                    <th>Confidence Score</th>
                                </tr>
                                ${evaluations.map((e, i) => `
                                    <tr>
                                        <td>${labels[i]}</td>
                                        <td>${e.stability.consistency.meanCorrelation.toFixed(4)}</td>
                                        <td>${e.reliability.score.toFixed(4)}</td>
                                        <td>${e.reliability.confidence.toFixed(4)}</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>

                        <div class="section">
                            <h2>Performance Comparison</h2>
                            <div class="chart">
                                <img src="training-time-comparison.png" alt="Training Time Comparison">
                            </div>
                            <div class="chart">
                                <img src="resource-usage-comparison.png" alt="Resource Usage Comparison">
                            </div>
                            <table>
                                <tr>
                                    <th>Model</th>
                                    <th>Training Time (s)</th>
                                    <th>Memory Usage (MB)</th>
                                    <th>CPU Usage (%)</th>
                                </tr>
                                ${evaluations.map((e, i) => `
                                    <tr>
                                        <td>${labels[i]}</td>
                                        <td>${e.performance.trainingTime.toFixed(2)}</td>
                                        <td>${e.performance.memoryUsage.toFixed(2)}</td>
                                        <td>${e.performance.cpuUsage.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>

                        <div class="section">
                            <h2>Key Findings</h2>
                            ${this.generateKeyFindings(evaluations, labels)}
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'comparison-report.html'), html);
    }

    /**
     * Generate key findings
     */
    generateKeyFindings(evaluations, labels) {
        const findings = [];

        // Accuracy improvements
        const accuracyChanges = this.calculateChanges(
            evaluations.map(e => e.accuracy.overall.score)
        );
        if (accuracyChanges.improvement > 0) {
            findings.push(`
                <div class="improvement">
                    <strong>Accuracy Improvement:</strong>
                    ${accuracyChanges.improvement.toFixed(2)}% improvement in accuracy
                    from ${labels[accuracyChanges.fromIndex]} to ${labels[accuracyChanges.toIndex]}
                </div>
            `);
        }

        // Performance changes
        const timeChanges = this.calculateChanges(
            evaluations.map(e => e.performance.trainingTime)
        );
        if (timeChanges.improvement < 0) {
            findings.push(`
                <div class="improvement">
                    <strong>Performance Improvement:</strong>
                    ${Math.abs(timeChanges.improvement).toFixed(2)}% reduction in training time
                    from ${labels[timeChanges.fromIndex]} to ${labels[timeChanges.toIndex]}
                </div>
            `);
        }

        // Stability improvements
        const stabilityChanges = this.calculateChanges(
            evaluations.map(e => e.stability.consistency.meanCorrelation)
        );
        if (stabilityChanges.improvement > 0) {
            findings.push(`
                <div class="improvement">
                    <strong>Stability Improvement:</strong>
                    ${stabilityChanges.improvement.toFixed(2)}% improvement in stability
                    from ${labels[stabilityChanges.fromIndex]} to ${labels[stabilityChanges.toIndex]}
                </div>
            `);
        }

        return findings.join('');
    }

    /**
     * Calculate changes between versions
     */
    calculateChanges(values) {
        let maxImprovement = -Infinity;
        let fromIndex = 0;
        let toIndex = 0;

        for (let i = 0; i < values.length - 1; i++) {
            for (let j = i + 1; j < values.length; j++) {
                const improvement = ((values[j] - values[i]) / values[i]) * 100;
                if (improvement > maxImprovement) {
                    maxImprovement = improvement;
                    fromIndex = i;
                    toIndex = j;
                }
            }
        }

        return {
            improvement: maxImprovement,
            fromIndex,
            toIndex
        };
    }

    /**
     * Save chart to file
     */
    async saveChart(filename, config) {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

module.exports = ComparativeVisualizer;
