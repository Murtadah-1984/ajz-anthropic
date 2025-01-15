const { ChartJSNodeCanvas } = require('chartjs-node-canvas');
const fs = require('fs');
const path = require('path');

/**
 * Statistical visualization utilities
 */
class StatisticalVisualizer {
    constructor(outputDir = 'statistical-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 1000,
            height: 500,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Generate statistical visualizations
     */
    async visualizeStatistics(statistics, labels) {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        await Promise.all([
            this.generateSignificanceVisuals(statistics.significance, labels),
            this.generateCorrelationVisuals(statistics.correlations, labels),
            this.generateDistributionVisuals(statistics.distributions, labels),
            this.generateEffectSizeVisuals(statistics.effectSizes, labels),
            this.generateRegressionVisuals(statistics.regressionAnalysis, labels),
            this.generateStatisticalReport(statistics, labels)
        ]);
    }

    /**
     * Generate significance visualizations
     */
    async generateSignificanceVisuals(significance, labels) {
        // P-value heatmap
        const pValueData = {
            labels,
            datasets: Object.entries(significance).map(([metric, tests]) => ({
                label: metric,
                data: tests.map(test => ({
                    x: test.comparison.split('_vs_')[0],
                    y: test.comparison.split('_vs_')[1],
                    v: test.pValue
                }))
            }))
        };

        const pValueConfig = {
            type: 'matrix',
            data: pValueData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Statistical Significance (p-values)'
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => `p-value: ${context.raw.v.toFixed(4)}`
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Model Version'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Model Version'
                        }
                    }
                }
            }
        };

        await this.saveChart('significance-heatmap.png', pValueConfig);

        // T-statistic forest plot
        const tStatData = {
            labels: Object.entries(significance).map(([metric]) => metric),
            datasets: [{
                label: 'T-Statistics',
                data: Object.entries(significance).map(([_, tests]) =>
                    tests.map(test => test.tValue)
                ).flat(),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };

        const tStatConfig = {
            type: 'box',
            data: tStatData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'T-Statistic Distribution'
                    }
                }
            }
        };

        await this.saveChart('t-statistics.png', tStatConfig);
    }

    /**
     * Generate correlation visualizations
     */
    async generateCorrelationVisuals(correlations, labels) {
        // Correlation matrix
        const correlationData = {
            labels: Object.keys(correlations),
            datasets: [{
                label: 'Correlation Coefficient',
                data: Object.values(correlations).map(c => c.correlation),
                backgroundColor: (context) => {
                    const value = context.raw;
                    return value > 0
                        ? `rgba(75, 192, 192, ${Math.abs(value)})`
                        : `rgba(255, 99, 132, ${Math.abs(value)})`;
                }
            }]
        };

        const correlationConfig = {
            type: 'matrix',
            data: correlationData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Metric Correlations'
                    }
                }
            }
        };

        await this.saveChart('correlation-matrix.png', correlationConfig);

        // Significance scatter plot
        const significanceData = {
            datasets: Object.entries(correlations).map(([pair, data]) => ({
                label: pair,
                data: [{
                    x: data.correlation,
                    y: -Math.log10(data.significance.pValue)
                }],
                backgroundColor: data.significance.significant
                    ? 'rgba(75, 192, 192, 0.5)'
                    : 'rgba(255, 99, 132, 0.5)'
            }))
        };

        const significanceConfig = {
            type: 'scatter',
            data: significanceData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Correlation Significance'
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Correlation Coefficient'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: '-log10(p-value)'
                        }
                    }
                }
            }
        };

        await this.saveChart('correlation-significance.png', significanceConfig);
    }

    /**
     * Generate distribution visualizations
     */
    async generateDistributionVisuals(distributions, labels) {
        // Q-Q plots
        for (const [metric, distribution] of Object.entries(distributions)) {
            const qqData = this.generateQQPlotData(distribution.statistics);
            const qqConfig = {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Q-Q Plot',
                        data: qqData,
                        showLine: true
                    }]
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: `${metric} Q-Q Plot`
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Theoretical Quantiles'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Sample Quantiles'
                            }
                        }
                    }
                }
            };

            await this.saveChart(`qq-plot-${metric}.png`, qqConfig);
        }

        // Box plots
        const boxplotData = {
            labels: Object.keys(distributions),
            datasets: [{
                label: 'Distribution',
                data: Object.values(distributions).map(d => ({
                    min: d.statistics.quantiles.q1 - 1.5 * (d.statistics.quantiles.q3 - d.statistics.quantiles.q1),
                    q1: d.statistics.quantiles.q1,
                    median: d.statistics.quantiles.q2,
                    q3: d.statistics.quantiles.q3,
                    max: d.statistics.quantiles.q3 + 1.5 * (d.statistics.quantiles.q3 - d.statistics.quantiles.q1),
                    outliers: d.outliers.outliers.map(o => o.value)
                }))
            }]
        };

        const boxplotConfig = {
            type: 'boxplot',
            data: boxplotData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Metric Distributions'
                    }
                }
            }
        };

        await this.saveChart('distributions-boxplot.png', boxplotConfig);
    }

    /**
     * Generate effect size visualizations
     */
    async generateEffectSizeVisuals(effectSizes, labels) {
        // Effect size comparison
        const effectData = {
            labels: Object.keys(effectSizes),
            datasets: [
                {
                    label: "Cohen's d",
                    data: Object.values(effectSizes).map(e => e.cohensD?.value || 0),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: "Hedge's g",
                    data: Object.values(effectSizes).map(e => e.hedgesG?.value || 0),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        };

        const effectConfig = {
            type: 'bar',
            data: effectData,
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Effect Sizes'
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Effect Size'
                        }
                    }
                }
            }
        };

        await this.saveChart('effect-sizes.png', effectConfig);
    }

    /**
     * Generate regression visualizations
     */
    async generateRegressionVisuals(regressionAnalysis, labels) {
        // Regression plots
        for (const [metric, analysis] of Object.entries(regressionAnalysis)) {
            const regressionData = {
                datasets: [
                    {
                        label: 'Actual Values',
                        data: analysis.regression.predictions.map((p, i) => ({
                            x: i,
                            y: analysis.regression.residuals[i] + p
                        })),
                        backgroundColor: 'rgba(75, 192, 192, 0.5)'
                    },
                    {
                        label: 'Regression Line',
                        data: analysis.regression.predictions.map((p, i) => ({
                            x: i,
                            y: p
                        })),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false
                    }
                ]
            };

            const regressionConfig = {
                type: 'scatter',
                data: regressionData,
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: `${metric} Regression Analysis`
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Value'
                            }
                        }
                    }
                }
            };

            await this.saveChart(`regression-${metric}.png`, regressionConfig);
        }
    }

    /**
     * Generate statistical report
     */
    async generateStatisticalReport(statistics, labels) {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Statistical Analysis Report</title>
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
                        .significant {
                            color: #4caf50;
                            font-weight: bold;
                        }
                        .not-significant {
                            color: #f44336;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>Statistical Analysis Report</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Significance Analysis</h2>
                            <div class="chart">
                                <img src="significance-heatmap.png" alt="Significance Heatmap">
                            </div>
                            <div class="chart">
                                <img src="t-statistics.png" alt="T-Statistics">
                            </div>
                            ${this.generateSignificanceTable(statistics.significance)}
                        </div>

                        <div class="section">
                            <h2>Correlation Analysis</h2>
                            <div class="chart">
                                <img src="correlation-matrix.png" alt="Correlation Matrix">
                            </div>
                            <div class="chart">
                                <img src="correlation-significance.png" alt="Correlation Significance">
                            </div>
                            ${this.generateCorrelationTable(statistics.correlations)}
                        </div>

                        <div class="section">
                            <h2>Distribution Analysis</h2>
                            <div class="chart">
                                <img src="distributions-boxplot.png" alt="Distribution Boxplots">
                            </div>
                            ${this.generateDistributionTables(statistics.distributions)}
                        </div>

                        <div class="section">
                            <h2>Effect Size Analysis</h2>
                            <div class="chart">
                                <img src="effect-sizes.png" alt="Effect Sizes">
                            </div>
                            ${this.generateEffectSizeTable(statistics.effectSizes)}
                        </div>

                        <div class="section">
                            <h2>Regression Analysis</h2>
                            ${Object.keys(statistics.regressionAnalysis).map(metric => `
                                <div class="chart">
                                    <img src="regression-${metric}.png" alt="${metric} Regression">
                                </div>
                            `).join('')}
                            ${this.generateRegressionTable(statistics.regressionAnalysis)}
                        </div>

                        <div class="section">
                            <h2>Key Findings</h2>
                            ${this.generateKeyFindings(statistics)}
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'statistical-report.html'), html);
    }

    /**
     * Generate Q-Q plot data
     */
    generateQQPlotData(statistics) {
        const n = 100;
        const theoreticalQuantiles = Array.from({ length: n }, (_, i) =>
            stats.probit((i + 1) / (n + 1))
        );

        const mean = statistics.mean;
        const std = statistics.standardDeviation;
        const sampleQuantiles = theoreticalQuantiles.map(q =>
            mean + std * q
        );

        return theoreticalQuantiles.map((q, i) => ({
            x: q,
            y: sampleQuantiles[i]
        }));
    }

    /**
     * Save chart to file
     */
    async saveChart(filename, config) {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

module.exports = StatisticalVisualizer;
