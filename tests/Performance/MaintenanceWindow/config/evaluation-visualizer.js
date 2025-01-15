const { ChartJSNodeCanvas } = require('chartjs-node-canvas');
const fs = require('fs');
const path = require('path');

/**
 * Model evaluation visualization utilities
 */
class EvaluationVisualizer {
    constructor(outputDir = 'evaluation-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 800,
            height: 400,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Generate evaluation visualizations
     */
    async visualizeEvaluation(evaluation) {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        await Promise.all([
            this.generateAccuracyCharts(evaluation.accuracy),
            this.generateMetricsCharts(evaluation.metrics),
            this.generateValidationCharts(evaluation.validation),
            this.generateStabilityCharts(evaluation.stability),
            this.generateReliabilityCharts(evaluation.reliability),
            this.generateSummaryReport(evaluation)
        ]);
    }

    /**
     * Generate accuracy charts
     */
    async generateAccuracyCharts(accuracy) {
        // Range accuracy chart
        const rangeData = {
            labels: Object.keys(accuracy.byRange),
            datasets: [{
                label: 'Accuracy Score',
                data: Object.values(accuracy.byRange).map(r => r.score),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };

        const rangeConfig = {
            type: 'bar',
            data: rangeData,
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
                        text: 'Accuracy by Value Range'
                    }
                }
            }
        };

        await this.saveChart('accuracy-range.png', rangeConfig);

        // Error distribution chart
        const errorDistribution = this.calculateErrorDistribution(accuracy);
        const errorData = {
            labels: Object.keys(errorDistribution),
            datasets: [{
                label: 'Error Frequency',
                data: Object.values(errorDistribution),
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
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
                            text: 'Frequency'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Error Distribution'
                    }
                }
            }
        };

        await this.saveChart('error-distribution.png', errorConfig);
    }

    /**
     * Generate metrics charts
     */
    async generateMetricsCharts(metrics) {
        // Metrics comparison chart
        const metricsData = {
            labels: Object.keys(metrics),
            datasets: [{
                label: 'Metric Values',
                data: Object.values(metrics),
                backgroundColor: Object.keys(metrics).map((_, i) =>
                    `hsla(${(i * 360) / Object.keys(metrics).length}, 70%, 50%, 0.2)`
                ),
                borderColor: Object.keys(metrics).map((_, i) =>
                    `hsla(${(i * 360) / Object.keys(metrics).length}, 70%, 50%, 1)`
                ),
                borderWidth: 1
            }]
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
                        text: 'Model Metrics Comparison'
                    }
                }
            }
        };

        await this.saveChart('metrics-comparison.png', metricsConfig);
    }

    /**
     * Generate validation charts
     */
    async generateValidationCharts(validation) {
        // Cross-validation scores
        const scoresData = {
            labels: Object.keys(validation.scores),
            datasets: Object.keys(validation.scores[Object.keys(validation.scores)[0]]).map((key, i) => ({
                label: key,
                data: Object.values(validation.scores).map(s => s[key]),
                backgroundColor: `rgba(75, 192, 192, ${0.2 + i * 0.2})`,
                borderColor: `rgba(75, 192, 192, ${0.8 + i * 0.2})`,
                borderWidth: 1
            }))
        };

        const scoresConfig = {
            type: 'bar',
            data: scoresData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Score Value'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Cross-Validation Scores'
                    }
                }
            }
        };

        await this.saveChart('validation-scores.png', scoresConfig);
    }

    /**
     * Generate stability charts
     */
    async generateStabilityCharts(stability) {
        // Prediction variability chart
        const variabilityData = {
            labels: Array.from({ length: stability.variability.length }, (_, i) => `Run ${i + 1}`),
            datasets: [{
                label: 'Prediction Variance',
                data: stability.variability,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        };

        const variabilityConfig = {
            type: 'line',
            data: variabilityData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Variance'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Prediction Variability'
                    }
                }
            }
        };

        await this.saveChart('stability-variability.png', variabilityConfig);
    }

    /**
     * Generate reliability charts
     */
    async generateReliabilityCharts(reliability) {
        // Reliability factors chart
        const factorsData = {
            labels: Object.keys(reliability.factors),
            datasets: [{
                label: 'Factor Score',
                data: Object.values(reliability.factors),
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        };

        const factorsConfig = {
            type: 'polarArea',
            data: factorsData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Reliability Factors'
                    }
                }
            }
        };

        await this.saveChart('reliability-factors.png', factorsConfig);
    }

    /**
     * Generate summary report
     */
    async generateSummaryReport(evaluation) {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Model Evaluation Results</title>
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
                        <h1>Model Evaluation Results</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Accuracy Analysis</h2>
                            <div class="chart">
                                <img src="accuracy-range.png" alt="Accuracy by Range">
                            </div>
                            <div class="chart">
                                <img src="error-distribution.png" alt="Error Distribution">
                            </div>
                            <table>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                                <tr>
                                    <td>Overall Accuracy</td>
                                    <td>${evaluation.accuracy.overall.score.toFixed(4)}</td>
                                </tr>
                                <tr>
                                    <td>MSE</td>
                                    <td>${evaluation.accuracy.overall.mse.toFixed(4)}</td>
                                </tr>
                                <tr>
                                    <td>RMSE</td>
                                    <td>${evaluation.accuracy.overall.rmse.toFixed(4)}</td>
                                </tr>
                            </table>
                        </div>

                        <div class="section">
                            <h2>Cross-Validation Results</h2>
                            <div class="chart">
                                <img src="validation-scores.png" alt="Validation Scores">
                            </div>
                            <table>
                                <tr>
                                    <th>Metric</th>
                                    <th>Mean</th>
                                    <th>Std Dev</th>
                                </tr>
                                ${Object.entries(evaluation.validation.scores).map(([metric, scores]) => `
                                    <tr>
                                        <td>${metric}</td>
                                        <td>${scores.mean.toFixed(4)}</td>
                                        <td>${scores.std.toFixed(4)}</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>

                        <div class="section">
                            <h2>Stability Analysis</h2>
                            <div class="chart">
                                <img src="stability-variability.png" alt="Stability Variability">
                            </div>
                            <div class="metric">
                                <span>Mean Variability:</span>
                                <span>${evaluation.stability.variability.mean.toFixed(4)}</span>
                            </div>
                            <div class="metric">
                                <span>Consistency Score:</span>
                                <span>${evaluation.stability.consistency.meanCorrelation.toFixed(4)}</span>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Reliability Analysis</h2>
                            <div class="chart">
                                <img src="reliability-factors.png" alt="Reliability Factors">
                            </div>
                            <div class="metric">
                                <span>Overall Reliability Score:</span>
                                <span>${evaluation.reliability.score.toFixed(4)}</span>
                            </div>
                            <div class="metric">
                                <span>Confidence Score:</span>
                                <span>${evaluation.reliability.confidence.toFixed(4)}</span>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Recommendations</h2>
                            ${evaluation.recommendations.map(rec => `
                                <div class="recommendation">
                                    <strong>${rec.type} (${rec.priority}):</strong>
                                    <p>${rec.message}</p>
                                    <ul>
                                        ${rec.actions.map(action => `<li>${action}</li>`).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'evaluation-report.html'), html);
    }

    /**
     * Calculate error distribution
     */
    calculateErrorDistribution(accuracy) {
        const errors = accuracy.errors;
        const bins = 10;
        const max = Math.max(...errors);
        const min = Math.min(...errors);
        const binSize = (max - min) / bins;

        const distribution = {};
        for (let i = 0; i < bins; i++) {
            const binStart = min + i * binSize;
            const binEnd = binStart + binSize;
            const binLabel = `${binStart.toFixed(2)}-${binEnd.toFixed(2)}`;
            distribution[binLabel] = errors.filter(e => e >= binStart && e < binEnd).length;
        }

        return distribution;
    }

    /**
     * Save chart to file
     */
    async saveChart(filename, config) {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

module.exports = EvaluationVisualizer;
