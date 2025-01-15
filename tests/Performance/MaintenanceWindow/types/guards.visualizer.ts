import * as fs from 'fs';
import * as path from 'path';
import { ChartJSNodeCanvas } from 'chartjs-node-canvas';
import type { BenchmarkStats, BenchmarkImprovement } from './guards.benchmark';

/**
 * Benchmark visualization utilities
 */
class BenchmarkVisualizer {
    private chartJs: ChartJSNodeCanvas;
    private outputDir: string;

    constructor(outputDir: string = 'benchmark-results') {
        this.outputDir = outputDir;
        this.chartJs = new ChartJSNodeCanvas({
            width: 1000,
            height: 500,
            backgroundColour: 'white'
        });

        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
    }

    /**
     * Generate visualization for benchmark comparison
     */
    async visualizeComparison(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        await Promise.all([
            this.generateExecutionTimeChart(name, original, optimized, improvement),
            this.generateMemoryUsageChart(name, original, optimized, improvement),
            this.generateGCImpactChart(name, original, optimized, improvement),
            this.generateImprovementSummaryChart(name, improvement),
            this.generateHTMLReport(name, original, optimized, improvement)
        ]);
    }

    /**
     * Generate execution time comparison chart
     */
    private async generateExecutionTimeChart(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        const config = {
            type: 'bar',
            data: {
                labels: ['Mean', 'Median', 'P95'],
                datasets: [
                    {
                        label: 'Original',
                        data: [
                            original.executionTime.mean,
                            original.executionTime.median,
                            original.executionTime.p95
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.5)'
                    },
                    {
                        label: 'Optimized',
                        data: [
                            optimized.executionTime.mean,
                            optimized.executionTime.median,
                            optimized.executionTime.p95
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)'
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Execution Time Comparison (ms)`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        await this.saveChart('execution-time.png', config);
    }

    /**
     * Generate memory usage comparison chart
     */
    private async generateMemoryUsageChart(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        const config = {
            type: 'bar',
            data: {
                labels: ['Mean', 'Peak', 'Growth'],
                datasets: [
                    {
                        label: 'Original',
                        data: [
                            original.memory.mean,
                            original.memory.peak,
                            original.memory.growth
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.5)'
                    },
                    {
                        label: 'Optimized',
                        data: [
                            optimized.memory.mean,
                            optimized.memory.peak,
                            optimized.memory.growth
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)'
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Memory Usage Comparison (MB)`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        await this.saveChart('memory-usage.png', config);
    }

    /**
     * Generate GC impact comparison chart
     */
    private async generateGCImpactChart(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        const config = {
            type: 'bar',
            data: {
                labels: ['Total Pauses', 'Mean Pause Time'],
                datasets: [
                    {
                        label: 'Original',
                        data: [
                            original.gc.totalPauses,
                            original.gc.meanPause
                        ],
                        backgroundColor: 'rgba(255, 99, 132, 0.5)'
                    },
                    {
                        label: 'Optimized',
                        data: [
                            optimized.gc.totalPauses,
                            optimized.gc.meanPause
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)'
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Garbage Collection Impact`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        await this.saveChart('gc-impact.png', config);
    }

    /**
     * Generate improvement summary chart
     */
    private async generateImprovementSummaryChart(
        name: string,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        const improvements = [
            improvement.executionTime.mean,
            improvement.executionTime.median,
            improvement.memory.mean,
            improvement.memory.peak,
            improvement.gc.pauses,
            improvement.gc.meanPause
        ];

        const config = {
            type: 'horizontalBar',
            data: {
                labels: [
                    'Execution Time (Mean)',
                    'Execution Time (Median)',
                    'Memory Usage (Mean)',
                    'Memory Usage (Peak)',
                    'GC Pauses',
                    'GC Pause Time'
                ],
                datasets: [{
                    label: 'Improvement %',
                    data: improvements.map(i => -i), // Negate to show improvements as positive
                    backgroundColor: improvements.map(i =>
                        i < 0 ? 'rgba(75, 192, 192, 0.5)' : 'rgba(255, 99, 132, 0.5)'
                    )
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Performance Improvements`
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Improvement %'
                        }
                    }
                }
            }
        };

        await this.saveChart('improvements.png', config);
    }

    /**
     * Generate HTML report with charts
     */
    private async generateHTMLReport(
        name: string,
        original: BenchmarkStats,
        optimized: BenchmarkStats,
        improvement: BenchmarkImprovement
    ): Promise<void> {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>${name} - Benchmark Results</title>
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
                        .improvement {
                            color: #4caf50;
                            font-weight: bold;
                        }
                        .regression {
                            color: #f44336;
                            font-weight: bold;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>${name} - Benchmark Results</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Execution Time Comparison</h2>
                            <div class="chart">
                                <img src="execution-time.png" alt="Execution Time Comparison">
                            </div>
                        </div>

                        <div class="section">
                            <h2>Memory Usage Comparison</h2>
                            <div class="chart">
                                <img src="memory-usage.png" alt="Memory Usage Comparison">
                            </div>
                        </div>

                        <div class="section">
                            <h2>Garbage Collection Impact</h2>
                            <div class="chart">
                                <img src="gc-impact.png" alt="GC Impact">
                            </div>
                        </div>

                        <div class="section">
                            <h2>Overall Improvements</h2>
                            <div class="chart">
                                <img src="improvements.png" alt="Improvements Summary">
                            </div>
                        </div>

                        <div class="section">
                            <h2>Key Metrics</h2>
                            <ul>
                                <li>Execution Time:
                                    <span class="${improvement.executionTime.mean < 0 ? 'improvement' : 'regression'}">
                                        ${Math.abs(improvement.executionTime.mean).toFixed(1)}%
                                        ${improvement.executionTime.mean < 0 ? 'faster' : 'slower'}
                                    </span>
                                </li>
                                <li>Memory Usage:
                                    <span class="${improvement.memory.mean < 0 ? 'improvement' : 'regression'}">
                                        ${Math.abs(improvement.memory.mean).toFixed(1)}%
                                        ${improvement.memory.mean < 0 ? 'lower' : 'higher'}
                                    </span>
                                </li>
                                <li>GC Impact:
                                    <span class="${improvement.gc.pauses < 0 ? 'improvement' : 'regression'}">
                                        ${Math.abs(improvement.gc.pauses).toFixed(1)}%
                                        ${improvement.gc.pauses < 0 ? 'fewer' : 'more'} pauses
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'benchmark-report.html'), html);
    }

    /**
     * Save chart to file
     */
    private async saveChart(filename: string, config: any): Promise<void> {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

export { BenchmarkVisualizer };
