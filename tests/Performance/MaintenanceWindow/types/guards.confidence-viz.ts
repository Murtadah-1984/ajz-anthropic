import * as fs from 'fs';
import * as path from 'path';
import { ChartJSNodeCanvas } from 'chartjs-node-canvas';
import type { PredictionIntervals, ReliabilityAnalysis } from './guards.confidence';
import type { BenchmarkStats } from './guards.benchmark';

/**
 * Confidence interval visualization
 */
class ConfidenceVisualizer {
    private chartJs: ChartJSNodeCanvas;
    private outputDir: string;

    constructor(outputDir: string = 'confidence-results') {
        this.outputDir = outputDir;
        this.chartJs = new ChartJSNodeCanvas({
            width: 1200,
            height: 600,
            backgroundColour: 'white'
        });

        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
    }

    /**
     * Visualize confidence intervals
     */
    async visualizeIntervals(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[],
        reliability: ReliabilityAnalysis
    ): Promise<void> {
        await Promise.all([
            this.generateExecutionTimeChart(name, historical, predictions, intervals),
            this.generateMemoryUsageChart(name, historical, predictions, intervals),
            this.generateGCActivityChart(name, historical, predictions, intervals),
            this.generateReliabilityChart(name, intervals, reliability),
            this.generateConfidenceReport(name, intervals, reliability)
        ]);
    }

    /**
     * Generate execution time confidence chart
     */
    private async generateExecutionTimeChart(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[]
    ): Promise<void> {
        const historicalTimes = historical.map(h => h.executionTime.mean);
        const predictedTimes = predictions.map(p => p.executionTime.mean);
        const timeLabels = [
            ...Array(historical.length).fill('').map((_, i) => `H${i + 1}`),
            ...Array(predictions.length).fill('').map((_, i) => `P${i + 1}`)
        ];

        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'Historical',
                        data: [...historicalTimes, ...Array(predictions.length).fill(null)],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: 'Predicted',
                        data: [...Array(historical.length).fill(null), ...predictedTimes],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false
                    },
                    {
                        label: 'Confidence Interval',
                        data: [
                            ...Array(historical.length).fill(null),
                            ...intervals.map(i => ({
                                y: predictedTimes[intervals.indexOf(i)],
                                yMin: i.executionTime.lower,
                                yMax: i.executionTime.upper
                            }))
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 0,
                        fill: true
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Execution Time Predictions with Confidence Intervals`
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Execution Time (ms)'
                        }
                    }
                }
            }
        };

        await this.saveChart('execution-confidence.png', config);
    }

    /**
     * Generate memory usage confidence chart
     */
    private async generateMemoryUsageChart(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[]
    ): Promise<void> {
        const historicalMemory = historical.map(h => h.memory.mean);
        const predictedMemory = predictions.map(p => p.memory.mean);
        const timeLabels = [
            ...Array(historical.length).fill('').map((_, i) => `H${i + 1}`),
            ...Array(predictions.length).fill('').map((_, i) => `P${i + 1}`)
        ];

        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'Historical',
                        data: [...historicalMemory, ...Array(predictions.length).fill(null)],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: 'Predicted',
                        data: [...Array(historical.length).fill(null), ...predictedMemory],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false
                    },
                    {
                        label: 'Confidence Interval',
                        data: [
                            ...Array(historical.length).fill(null),
                            ...intervals.map(i => ({
                                y: predictedMemory[intervals.indexOf(i)],
                                yMin: i.memory.lower,
                                yMax: i.memory.upper
                            }))
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 0,
                        fill: true
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Memory Usage Predictions with Confidence Intervals`
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Memory Usage (MB)'
                        }
                    }
                }
            }
        };

        await this.saveChart('memory-confidence.png', config);
    }

    /**
     * Generate GC activity confidence chart
     */
    private async generateGCActivityChart(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[]
    ): Promise<void> {
        const historicalGC = historical.map(h => h.gc.totalPauses);
        const predictedGC = predictions.map(p => p.gc.totalPauses);
        const timeLabels = [
            ...Array(historical.length).fill('').map((_, i) => `H${i + 1}`),
            ...Array(predictions.length).fill('').map((_, i) => `P${i + 1}`)
        ];

        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'Historical',
                        data: [...historicalGC, ...Array(predictions.length).fill(null)],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: 'Predicted',
                        data: [...Array(historical.length).fill(null), ...predictedGC],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false
                    },
                    {
                        label: 'Confidence Interval',
                        data: [
                            ...Array(historical.length).fill(null),
                            ...intervals.map(i => ({
                                y: predictedGC[intervals.indexOf(i)],
                                yMin: i.gc.lower,
                                yMax: i.gc.upper
                            }))
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 0,
                        fill: true
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - GC Activity Predictions with Confidence Intervals`
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'GC Pauses'
                        }
                    }
                }
            }
        };

        await this.saveChart('gc-confidence.png', config);
    }

    /**
     * Generate reliability trend chart
     */
    private async generateReliabilityChart(
        name: string,
        intervals: PredictionIntervals[],
        reliability: ReliabilityAnalysis
    ): Promise<void> {
        const metrics = ['Execution Time', 'Memory Usage', 'GC Activity'];
        const trends = [
            reliability.trends.executionTime,
            reliability.trends.memory,
            reliability.trends.gc
        ];

        const config = {
            type: 'bar',
            data: {
                labels: metrics,
                datasets: [{
                    label: 'Reliability Trend',
                    data: trends,
                    backgroundColor: trends.map(t =>
                        t <= 0.1 ? 'rgba(75, 192, 192, 0.5)' :
                        t <= 0.2 ? 'rgba(255, 206, 86, 0.5)' :
                        'rgba(255, 99, 132, 0.5)'
                    )
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Prediction Reliability Trends`
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Trend in Interval Width'
                        }
                    }
                }
            }
        };

        await this.saveChart('reliability-trends.png', config);
    }

    /**
     * Generate confidence visualization report
     */
    private async generateConfidenceReport(
        name: string,
        intervals: PredictionIntervals[],
        reliability: ReliabilityAnalysis
    ): Promise<void> {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>${name} - Confidence Interval Analysis</title>
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
                        .metric {
                            margin: 10px 0;
                            padding: 10px;
                            background-color: #f8f9fa;
                            border-radius: 4px;
                        }
                        .reliability-good {
                            color: #28a745;
                        }
                        .reliability-warning {
                            color: #ffc107;
                        }
                        .reliability-poor {
                            color: #dc3545;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>${name} - Confidence Interval Analysis</h1>

                        <div class="section">
                            <h2>Execution Time Predictions</h2>
                            <div class="chart">
                                <img src="execution-confidence.png" alt="Execution Time Confidence">
                            </div>
                            <div class="metric">
                                <h3 class="${this.getReliabilityClass(reliability.trends.executionTime)}">
                                    Reliability: ${this.formatReliability(reliability.trends.executionTime)}
                                </h3>
                                <p>Interval Width Trend: ${(reliability.trends.executionTime * 100).toFixed(1)}%</p>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Memory Usage Predictions</h2>
                            <div class="chart">
                                <img src="memory-confidence.png" alt="Memory Usage Confidence">
                            </div>
                            <div class="metric">
                                <h3 class="${this.getReliabilityClass(reliability.trends.memory)}">
                                    Reliability: ${this.formatReliability(reliability.trends.memory)}
                                </h3>
                                <p>Interval Width Trend: ${(reliability.trends.memory * 100).toFixed(1)}%</p>
                            </div>
                        </div>

                        <div class="section">
                            <h2>GC Activity Predictions</h2>
                            <div class="chart">
                                <img src="gc-confidence.png" alt="GC Activity Confidence">
                            </div>
                            <div class="metric">
                                <h3 class="${this.getReliabilityClass(reliability.trends.gc)}">
                                    Reliability: ${this.formatReliability(reliability.trends.gc)}
                                </h3>
                                <p>Interval Width Trend: ${(reliability.trends.gc * 100).toFixed(1)}%</p>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Reliability Overview</h2>
                            <div class="chart">
                                <img src="reliability-trends.png" alt="Reliability Trends">
                            </div>
                            <div class="metric">
                                <h3>Recommendations</h3>
                                <ul>
                                    ${reliability.recommendations.map(rec =>
                                        `<li>${rec}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        </div>

                        <div class="section">
                            <h2>Interpretation Guide</h2>
                            <ul>
                                <li>Shaded areas represent the confidence intervals for predictions</li>
                                <li>Wider intervals indicate higher uncertainty in predictions</li>
                                <li>Growing interval widths suggest decreasing prediction reliability</li>
                                <li>Consider both the trend and absolute width when interpreting results</li>
                            </ul>
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'confidence-report.html'), html);
    }

    /**
     * Format reliability level
     */
    private formatReliability(trend: number): string {
        if (trend <= 0.1) return 'High';
        if (trend <= 0.2) return 'Moderate';
        return 'Low';
    }

    /**
     * Get reliability CSS class
     */
    private getReliabilityClass(trend: number): string {
        if (trend <= 0.1) return 'reliability-good';
        if (trend <= 0.2) return 'reliability-warning';
        return 'reliability-poor';
    }

    /**
     * Save chart to file
     */
    private async saveChart(filename: string, config: any): Promise<void> {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

export { ConfidenceVisualizer };
