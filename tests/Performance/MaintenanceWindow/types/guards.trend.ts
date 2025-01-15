import * as fs from 'fs';
import * as path from 'path';
import { ChartJSNodeCanvas } from 'chartjs-node-canvas';
import type { BenchmarkStats } from './guards.benchmark';

/**
 * Performance trend analysis visualization
 */
class TrendVisualizer {
    private chartJs: ChartJSNodeCanvas;
    private outputDir: string;

    constructor(outputDir: string = 'trend-results') {
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
     * Visualize performance trends over time
     */
    async visualizeTrends(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        await Promise.all([
            this.generateExecutionTrendChart(name, snapshots, timestamps),
            this.generateMemoryTrendChart(name, snapshots, timestamps),
            this.generateGCTrendChart(name, snapshots, timestamps),
            this.generatePerformanceHeatmap(name, snapshots, timestamps),
            this.generateTrendReport(name, snapshots, timestamps)
        ]);
    }

    /**
     * Generate execution time trend chart
     */
    private async generateExecutionTrendChart(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        const timeLabels = timestamps.map(t => t.toLocaleString());
        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'Mean Execution Time',
                        data: snapshots.map(s => s.executionTime.mean),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: '95th Percentile',
                        data: snapshots.map(s => s.executionTime.p95),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Execution Time Trend (ms)`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        await this.saveChart('execution-trend.png', config);
    }

    /**
     * Generate memory usage trend chart
     */
    private async generateMemoryTrendChart(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        const timeLabels = timestamps.map(t => t.toLocaleString());
        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'Mean Memory Usage',
                        data: snapshots.map(s => s.memory.mean),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false
                    },
                    {
                        label: 'Peak Memory Usage',
                        data: snapshots.map(s => s.memory.peak),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false
                    },
                    {
                        label: 'Memory Growth',
                        data: snapshots.map(s => s.memory.growth),
                        borderColor: 'rgba(255, 206, 86, 1)',
                        fill: false
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Memory Usage Trend (MB)`
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        await this.saveChart('memory-trend.png', config);
    }

    /**
     * Generate GC impact trend chart
     */
    private async generateGCTrendChart(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        const timeLabels = timestamps.map(t => t.toLocaleString());
        const config = {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [
                    {
                        label: 'GC Pauses',
                        data: snapshots.map(s => s.gc.totalPauses),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        fill: false,
                        yAxisID: 'pauses'
                    },
                    {
                        label: 'Mean Pause Duration',
                        data: snapshots.map(s => s.gc.meanPause),
                        borderColor: 'rgba(255, 99, 132, 1)',
                        fill: false,
                        yAxisID: 'duration'
                    }
                ]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Garbage Collection Trend`
                    }
                },
                scales: {
                    pauses: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Pauses'
                        }
                    },
                    duration: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pause Duration (ms)'
                        }
                    }
                }
            }
        };

        await this.saveChart('gc-trend.png', config);
    }

    /**
     * Generate performance heatmap
     */
    private async generatePerformanceHeatmap(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        const metrics = [
            'Execution Time',
            'Memory Usage',
            'GC Pauses',
            'GC Duration'
        ];

        const data = snapshots.map(s => [
            s.executionTime.mean,
            s.memory.mean,
            s.gc.totalPauses,
            s.gc.meanPause
        ]);

        // Normalize data for heatmap
        const normalizedData = this.normalizeData(data);

        const config = {
            type: 'matrix',
            data: {
                datasets: [{
                    label: 'Performance Heatmap',
                    data: normalizedData.flatMap((row, i) =>
                        row.map((value, j) => ({
                            x: j,
                            y: i,
                            v: value
                        }))
                    ),
                    backgroundColor: (context: any) => {
                        const value = context.raw.v;
                        const alpha = 0.2 + value * 0.8;
                        return value > 0.5
                            ? `rgba(255, 99, 132, ${alpha})`
                            : `rgba(75, 192, 192, ${alpha})`;
                    }
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `${name} - Performance Heatmap`
                    },
                    tooltip: {
                        callbacks: {
                            label: (context: any) => {
                                const value = context.raw.v;
                                return `Normalized Value: ${value.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Metrics'
                        },
                        ticks: {
                            callback: (value: number) => metrics[value]
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Time'
                        },
                        ticks: {
                            callback: (value: number) => timestamps[value].toLocaleString()
                        }
                    }
                }
            }
        };

        await this.saveChart('performance-heatmap.png', config);
    }

    /**
     * Generate trend analysis report
     */
    private async generateTrendReport(
        name: string,
        snapshots: BenchmarkStats[],
        timestamps: Date[]
    ): Promise<void> {
        const trends = this.analyzeTrends(snapshots);
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>${name} - Performance Trends</title>
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
                        .trend-positive {
                            color: #4caf50;
                            font-weight: bold;
                        }
                        .trend-negative {
                            color: #f44336;
                            font-weight: bold;
                        }
                        .trend-neutral {
                            color: #ff9800;
                            font-weight: bold;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>${name} - Performance Trends</h1>
                        <p>Analysis Period: ${timestamps[0].toLocaleString()} - ${timestamps[timestamps.length - 1].toLocaleString()}</p>

                        <div class="section">
                            <h2>Execution Time Trends</h2>
                            <div class="chart">
                                <img src="execution-trend.png" alt="Execution Time Trend">
                            </div>
                            <p class="trend-${this.getTrendClass(trends.executionTime)}">
                                ${this.formatTrend('Execution time', trends.executionTime)}
                            </p>
                        </div>

                        <div class="section">
                            <h2>Memory Usage Trends</h2>
                            <div class="chart">
                                <img src="memory-trend.png" alt="Memory Usage Trend">
                            </div>
                            <p class="trend-${this.getTrendClass(trends.memory)}">
                                ${this.formatTrend('Memory usage', trends.memory)}
                            </p>
                        </div>

                        <div class="section">
                            <h2>Garbage Collection Trends</h2>
                            <div class="chart">
                                <img src="gc-trend.png" alt="GC Trend">
                            </div>
                            <p class="trend-${this.getTrendClass(trends.gc)}">
                                ${this.formatTrend('GC impact', trends.gc)}
                            </p>
                        </div>

                        <div class="section">
                            <h2>Performance Heatmap</h2>
                            <div class="chart">
                                <img src="performance-heatmap.png" alt="Performance Heatmap">
                            </div>
                            <p>
                                The heatmap shows relative performance across different metrics over time.
                                Darker red indicates potential performance issues, while darker green indicates better performance.
                            </p>
                        </div>

                        <div class="section">
                            <h2>Recommendations</h2>
                            <ul>
                                ${this.generateRecommendations(trends).map(rec =>
                                    `<li>${rec}</li>`
                                ).join('')}
                            </ul>
                        </div>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'trend-report.html'), html);
    }

    /**
     * Analyze performance trends
     */
    private analyzeTrends(snapshots: BenchmarkStats[]): {
        executionTime: number;
        memory: number;
        gc: number;
    } {
        const getSlope = (values: number[]) => {
            const n = values.length;
            const indices = Array.from({ length: n }, (_, i) => i);
            const sumX = indices.reduce((a, b) => a + b, 0);
            const sumY = values.reduce((a, b) => a + b, 0);
            const sumXY = indices.reduce((sum, x, i) => sum + x * values[i], 0);
            const sumXX = indices.reduce((sum, x) => sum + x * x, 0);
            return (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
        };

        return {
            executionTime: getSlope(snapshots.map(s => s.executionTime.mean)),
            memory: getSlope(snapshots.map(s => s.memory.mean)),
            gc: getSlope(snapshots.map(s => s.gc.totalPauses))
        };
    }

    /**
     * Generate performance recommendations
     */
    private generateRecommendations(trends: {
        executionTime: number;
        memory: number;
        gc: number;
    }): string[] {
        const recommendations: string[] = [];

        if (trends.executionTime > 0) {
            recommendations.push(
                'Execution time is trending upward. Consider implementing caching or optimizing critical code paths.'
            );
        }

        if (trends.memory > 0) {
            recommendations.push(
                'Memory usage is increasing over time. Look for potential memory leaks or implement object pooling.'
            );
        }

        if (trends.gc > 0) {
            recommendations.push(
                'Increasing GC activity detected. Consider batch processing or reducing object allocations.'
            );
        }

        if (recommendations.length === 0) {
            recommendations.push(
                'Performance trends are stable or improving. Continue monitoring for any changes.'
            );
        }

        return recommendations;
    }

    /**
     * Utility functions
     */
    private normalizeData(data: number[][]): number[][] {
        const normalized = data.map(row => {
            const min = Math.min(...row);
            const max = Math.max(...row);
            return row.map(value =>
                max === min ? 0.5 : (value - min) / (max - min)
            );
        });
        return normalized;
    }

    private getTrendClass(trend: number): string {
        if (Math.abs(trend) < 0.01) return 'neutral';
        return trend < 0 ? 'positive' : 'negative';
    }

    private formatTrend(metric: string, trend: number): string {
        const direction = trend < 0 ? 'improving' : 'degrading';
        const magnitude = Math.abs(trend);
        let severity = 'slightly';
        if (magnitude > 0.1) severity = 'significantly';
        if (magnitude > 0.25) severity = 'dramatically';
        return `${metric} is ${severity} ${direction} over time (${(trend * 100).toFixed(1)}% per snapshot)`;
    }

    /**
     * Save chart to file
     */
    private async saveChart(filename: string, config: any): Promise<void> {
        const buffer = await this.chartJs.renderToBuffer(config);
        fs.writeFileSync(path.join(this.outputDir, filename), buffer);
    }
}

export { TrendVisualizer };
