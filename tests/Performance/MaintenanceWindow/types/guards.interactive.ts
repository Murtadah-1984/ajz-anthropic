import * as fs from 'fs';
import * as path from 'path';
import type { PredictionIntervals, ReliabilityAnalysis } from './guards.confidence';
import type { BenchmarkStats } from './guards.benchmark';

/**
 * Interactive confidence visualization
 */
class InteractiveVisualizer {
    private outputDir: string;

    constructor(outputDir: string = 'interactive-results') {
        this.outputDir = outputDir;
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
    }

    /**
     * Generate interactive visualization
     */
    async generateInteractive(
        name: string,
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[],
        reliability: ReliabilityAnalysis
    ): Promise<void> {
        const data = this.prepareData(historical, predictions, intervals);
        await this.generateHTML(name, data, reliability);
    }

    /**
     * Prepare data for visualization
     */
    private prepareData(
        historical: BenchmarkStats[],
        predictions: BenchmarkStats[],
        intervals: PredictionIntervals[]
    ): VisualizationData {
        return {
            executionTime: {
                historical: historical.map(h => ({
                    value: h.executionTime.mean,
                    timestamp: new Date().toISOString() // In real app, use actual timestamps
                })),
                predictions: predictions.map((p, i) => ({
                    value: p.executionTime.mean,
                    lower: intervals[i].executionTime.lower,
                    upper: intervals[i].executionTime.upper,
                    timestamp: new Date().toISOString()
                }))
            },
            memory: {
                historical: historical.map(h => ({
                    value: h.memory.mean,
                    timestamp: new Date().toISOString()
                })),
                predictions: predictions.map((p, i) => ({
                    value: p.memory.mean,
                    lower: intervals[i].memory.lower,
                    upper: intervals[i].memory.upper,
                    timestamp: new Date().toISOString()
                }))
            },
            gc: {
                historical: historical.map(h => ({
                    value: h.gc.totalPauses,
                    timestamp: new Date().toISOString()
                })),
                predictions: predictions.map((p, i) => ({
                    value: p.gc.totalPauses,
                    lower: intervals[i].gc.lower,
                    upper: intervals[i].gc.upper,
                    timestamp: new Date().toISOString()
                }))
            }
        };
    }

    /**
     * Generate interactive HTML report
     */
    private async generateHTML(
        name: string,
        data: VisualizationData,
        reliability: ReliabilityAnalysis
    ): Promise<void> {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>${name} - Interactive Performance Analysis</title>
                    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
                    <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
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
                        .chart-container {
                            height: 500px;
                            margin: 20px 0;
                        }
                        .controls {
                            margin: 20px 0;
                            padding: 10px;
                            background-color: #f8f9fa;
                            border-radius: 4px;
                        }
                        .tooltip {
                            position: absolute;
                            padding: 10px;
                            background: rgba(0, 0, 0, 0.8);
                            color: white;
                            border-radius: 4px;
                            font-size: 12px;
                            pointer-events: none;
                        }
                        .metric-card {
                            padding: 15px;
                            margin: 10px 0;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            transition: all 0.3s ease;
                        }
                        .metric-card:hover {
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>${name} - Interactive Performance Analysis</h1>

                        <div class="controls">
                            <label>
                                Visualization Type:
                                <select id="vizType" onchange="updateVisualization()">
                                    <option value="line">Line Chart</option>
                                    <option value="scatter">Scatter Plot</option>
                                    <option value="area">Area Chart</option>
                                </select>
                            </label>
                            <label>
                                Confidence Level:
                                <select id="confidenceLevel" onchange="updateConfidenceIntervals()">
                                    <option value="0.99">99%</option>
                                    <option value="0.95" selected>95%</option>
                                    <option value="0.90">90%</option>
                                </select>
                            </label>
                            <label>
                                <input type="checkbox" id="showTrend" onchange="toggleTrendLine()">
                                Show Trend Line
                            </label>
                        </div>

                        <div id="executionTimeChart" class="chart-container"></div>
                        <div id="memoryChart" class="chart-container"></div>
                        <div id="gcChart" class="chart-container"></div>

                        <div class="metric-cards">
                            <div class="metric-card" onclick="focusMetric('executionTime')">
                                <h3>Execution Time</h3>
                                <div id="executionTimeStats"></div>
                            </div>
                            <div class="metric-card" onclick="focusMetric('memory')">
                                <h3>Memory Usage</h3>
                                <div id="memoryStats"></div>
                            </div>
                            <div class="metric-card" onclick="focusMetric('gc')">
                                <h3>GC Activity</h3>
                                <div id="gcStats"></div>
                            </div>
                        </div>

                        <script>
                            // Data initialization
                            const data = ${JSON.stringify(data)};
                            const reliability = ${JSON.stringify(reliability)};

                            // Chart configuration
                            const config = {
                                executionTime: {
                                    title: 'Execution Time Predictions',
                                    yaxis: 'Time (ms)'
                                },
                                memory: {
                                    title: 'Memory Usage Predictions',
                                    yaxis: 'Memory (MB)'
                                },
                                gc: {
                                    title: 'GC Activity Predictions',
                                    yaxis: 'Pauses'
                                }
                            };

                            // Initialize visualizations
                            function initCharts() {
                                ['executionTime', 'memory', 'gc'].forEach(metric => {
                                    const chartData = createChartData(metric);
                                    Plotly.newPlot(
                                        metric + 'Chart',
                                        chartData,
                                        createLayout(config[metric])
                                    );
                                });
                                updateStats();
                            }

                            // Create chart data
                            function createChartData(metric) {
                                const historical = {
                                    name: 'Historical',
                                    x: data[metric].historical.map(d => d.timestamp),
                                    y: data[metric].historical.map(d => d.value),
                                    type: 'scatter',
                                    mode: 'lines+markers'
                                };

                                const predicted = {
                                    name: 'Predicted',
                                    x: data[metric].predictions.map(d => d.timestamp),
                                    y: data[metric].predictions.map(d => d.value),
                                    type: 'scatter',
                                    mode: 'lines+markers'
                                };

                                const confidence = {
                                    name: 'Confidence Interval',
                                    x: data[metric].predictions.map(d => d.timestamp)
                                        .concat(data[metric].predictions.map(d => d.timestamp).reverse()),
                                    y: data[metric].predictions.map(d => d.upper)
                                        .concat(data[metric].predictions.map(d => d.lower).reverse()),
                                    fill: 'toself',
                                    fillcolor: 'rgba(0,176,246,0.2)',
                                    line: { color: 'transparent' },
                                    showlegend: false
                                };

                                return [historical, predicted, confidence];
                            }

                            // Create chart layout
                            function createLayout(config) {
                                return {
                                    title: config.title,
                                    xaxis: {
                                        title: 'Time'
                                    },
                                    yaxis: {
                                        title: config.yaxis
                                    },
                                    hovermode: 'closest',
                                    showlegend: true
                                };
                            }

                            // Update statistics
                            function updateStats() {
                                ['executionTime', 'memory', 'gc'].forEach(metric => {
                                    const stats = calculateStats(metric);
                                    document.getElementById(metric + 'Stats').innerHTML = \`
                                        <p>Mean: \${stats.mean.toFixed(2)}</p>
                                        <p>Trend: \${formatTrend(stats.trend)}</p>
                                        <p>Reliability: \${formatReliability(reliability.trends[metric])}</p>
                                    \`;
                                });
                            }

                            // Calculate statistics
                            function calculateStats(metric) {
                                const values = data[metric].historical.map(d => d.value);
                                return {
                                    mean: values.reduce((a, b) => a + b, 0) / values.length,
                                    trend: (values[values.length - 1] - values[0]) / values[0]
                                };
                            }

                            // Format trend
                            function formatTrend(trend) {
                                const direction = trend > 0 ? 'increasing' : 'decreasing';
                                return \`\${Math.abs(trend * 100).toFixed(1)}% \${direction}\`;
                            }

                            // Format reliability
                            function formatReliability(trend) {
                                if (trend <= 0.1) return 'High';
                                if (trend <= 0.2) return 'Moderate';
                                return 'Low';
                            }

                            // Update visualization type
                            function updateVisualization() {
                                const type = document.getElementById('vizType').value;
                                ['executionTime', 'memory', 'gc'].forEach(metric => {
                                    const update = {
                                        'scatter': { type: 'scatter', mode: 'markers' },
                                        'line': { type: 'scatter', mode: 'lines+markers' },
                                        'area': { type: 'scatter', fill: 'tonexty' }
                                    }[type];

                                    Plotly.restyle(metric + 'Chart', update);
                                });
                            }

                            // Update confidence intervals
                            function updateConfidenceIntervals() {
                                const level = parseFloat(document.getElementById('confidenceLevel').value);
                                ['executionTime', 'memory', 'gc'].forEach(metric => {
                                    const multiplier = {
                                        0.99: 2.576,
                                        0.95: 1.96,
                                        0.90: 1.645
                                    }[level];

                                    const newIntervals = data[metric].predictions.map(d => ({
                                        lower: d.value - (d.value - d.lower) * multiplier / 1.96,
                                        upper: d.value + (d.upper - d.value) * multiplier / 1.96
                                    }));

                                    const update = {
                                        y: [
                                            ...newIntervals.map(i => i.upper),
                                            ...newIntervals.map(i => i.lower).reverse()
                                        ]
                                    };

                                    Plotly.restyle(metric + 'Chart', update, [2]);
                                });
                            }

                            // Toggle trend line
                            function toggleTrendLine() {
                                const showTrend = document.getElementById('showTrend').checked;
                                ['executionTime', 'memory', 'gc'].forEach(metric => {
                                    const values = data[metric].historical.map(d => d.value);
                                    if (showTrend) {
                                        const trend = calculateTrendLine(values);
                                        const trendData = {
                                            name: 'Trend',
                                            x: data[metric].historical.map(d => d.timestamp),
                                            y: trend,
                                            type: 'scatter',
                                            mode: 'lines',
                                            line: { dash: 'dot' }
                                        };
                                        Plotly.addTraces(metric + 'Chart', trendData);
                                    } else {
                                        Plotly.deleteTraces(metric + 'Chart', -1);
                                    }
                                });
                            }

                            // Calculate trend line
                            function calculateTrendLine(values) {
                                const n = values.length;
                                const x = Array.from({ length: n }, (_, i) => i);
                                const sumX = x.reduce((a, b) => a + b, 0);
                                const sumY = values.reduce((a, b) => a + b, 0);
                                const sumXY = x.reduce((sum, x, i) => sum + x * values[i], 0);
                                const sumXX = x.reduce((sum, x) => sum + x * x, 0);

                                const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
                                const intercept = (sumY - slope * sumX) / n;

                                return x.map(x => slope * x + intercept);
                            }

                            // Focus on metric
                            function focusMetric(metric) {
                                ['executionTime', 'memory', 'gc'].forEach(m => {
                                    const chart = document.getElementById(m + 'Chart');
                                    chart.style.opacity = m === metric ? '1' : '0.5';
                                });
                            }

                            // Initialize on load
                            document.addEventListener('DOMContentLoaded', initCharts);
                        </script>
                    </div>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'interactive.html'), html);
    }
}

interface DataPoint {
    value: number;
    timestamp: string;
}

interface PredictionPoint extends DataPoint {
    lower: number;
    upper: number;
}

interface MetricData {
    historical: DataPoint[];
    predictions: PredictionPoint[];
}

interface VisualizationData {
    executionTime: MetricData;
    memory: MetricData;
    gc: MetricData;
}

export { InteractiveVisualizer };
