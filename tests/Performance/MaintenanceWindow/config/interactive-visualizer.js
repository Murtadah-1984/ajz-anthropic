const { ChartJSNodeCanvas } = require('chartjs-node-canvas');
const fs = require('fs');
const path = require('path');

/**
 * Interactive visualization utilities
 */
class InteractiveVisualizer {
    constructor(outputDir = 'interactive-results') {
        this.outputDir = outputDir;
        this.chartConfig = {
            width: 1000,
            height: 500,
            backgroundColour: 'white'
        };
        this.chartJs = new ChartJSNodeCanvas(this.chartConfig);
    }

    /**
     * Generate interactive visualizations
     */
    async generateInteractiveVisuals(statistics, labels) {
        // Create output directory
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }

        // Generate interactive HTML report
        await this.generateInteractiveReport(statistics, labels);

        // Generate supporting JavaScript files
        await this.generateVisualizationScripts();
        await this.generateDataFiles(statistics, labels);
    }

    /**
     * Generate interactive report
     */
    async generateInteractiveReport(statistics, labels) {
        const html = `
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Interactive Statistical Analysis</title>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="https://cdn.jsdelivr.net/npm/d3"></script>
                    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
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
                        .chart-container {
                            position: relative;
                            height: 500px;
                            margin: 20px 0;
                        }
                        .controls {
                            margin: 10px 0;
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
                            pointer-events: none;
                        }
                        .highlight {
                            stroke-width: 3px;
                            stroke: #ff0000;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>Interactive Statistical Analysis</h1>
                        <p>Generated at: ${new Date().toISOString()}</p>

                        <div class="section">
                            <h2>Significance Analysis</h2>
                            <div class="controls">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="updateSignificanceView('heatmap')">Heatmap</button>
                                    <button class="btn btn-outline-primary" onclick="updateSignificanceView('network')">Network</button>
                                    <button class="btn btn-outline-primary" onclick="updateSignificanceView('tree')">Tree</button>
                                </div>
                            </div>
                            <div class="chart-container" id="significance-chart"></div>
                        </div>

                        <div class="section">
                            <h2>Correlation Analysis</h2>
                            <div class="controls">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="updateCorrelationView('matrix')">Matrix</button>
                                    <button class="btn btn-outline-primary" onclick="updateCorrelationView('force')">Force Directed</button>
                                    <button class="btn btn-outline-primary" onclick="updateCorrelationView('chord')">Chord</button>
                                </div>
                            </div>
                            <div class="chart-container" id="correlation-chart"></div>
                        </div>

                        <div class="section">
                            <h2>Distribution Analysis</h2>
                            <div class="controls">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="updateDistributionView('violin')">Violin</button>
                                    <button class="btn btn-outline-primary" onclick="updateDistributionView('box')">Box</button>
                                    <button class="btn btn-outline-primary" onclick="updateDistributionView('histogram')">Histogram</button>
                                </div>
                                <select class="form-select" id="metric-selector" onchange="updateMetricView()">
                                    ${Object.keys(statistics.distributions).map(metric =>
                                        `<option value="${metric}">${metric}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="chart-container" id="distribution-chart"></div>
                        </div>

                        <div class="section">
                            <h2>Regression Analysis</h2>
                            <div class="controls">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="updateRegressionView('line')">Line</button>
                                    <button class="btn btn-outline-primary" onclick="updateRegressionView('scatter')">Scatter</button>
                                    <button class="btn btn-outline-primary" onclick="updateRegressionView('residuals')">Residuals</button>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confidence-intervals" onchange="toggleConfidenceIntervals()">
                                    <label class="form-check-label" for="confidence-intervals">Show Confidence Intervals</label>
                                </div>
                            </div>
                            <div class="chart-container" id="regression-chart"></div>
                        </div>

                        <div class="section">
                            <h2>Effect Size Analysis</h2>
                            <div class="controls">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" onclick="updateEffectView('bar')">Bar</button>
                                    <button class="btn btn-outline-primary" onclick="updateEffectView('radar')">Radar</button>
                                    <button class="btn btn-outline-primary" onclick="updateEffectView('bubble')">Bubble</button>
                                </div>
                            </div>
                            <div class="chart-container" id="effect-chart"></div>
                        </div>
                    </div>

                    <script src="visualization.js"></script>
                    <script>
                        // Initialize visualizations with data
                        const statisticsData = ${JSON.stringify(statistics)};
                        const chartLabels = ${JSON.stringify(labels)};
                        initializeVisualizations(statisticsData, chartLabels);
                    </script>
                </body>
            </html>
        `;

        fs.writeFileSync(path.join(this.outputDir, 'interactive-report.html'), html);
    }

    /**
     * Generate visualization scripts
     */
    async generateVisualizationScripts() {
        const script = `
            // Visualization state
            let currentViews = {
                significance: 'heatmap',
                correlation: 'matrix',
                distribution: 'violin',
                regression: 'line',
                effect: 'bar'
            };

            let charts = {};
            let data;
            let labels;

            /**
             * Initialize visualizations
             */
            function initializeVisualizations(statisticsData, chartLabels) {
                data = statisticsData;
                labels = chartLabels;

                // Initialize all charts
                initializeSignificanceChart();
                initializeCorrelationChart();
                initializeDistributionChart();
                initializeRegressionChart();
                initializeEffectChart();

                // Add event listeners
                addInteractiveFeatures();
            }

            /**
             * Initialize significance chart
             */
            function initializeSignificanceChart() {
                const ctx = document.getElementById('significance-chart');
                charts.significance = new Chart(ctx, getSignificanceConfig());
            }

            /**
             * Initialize correlation chart
             */
            function initializeCorrelationChart() {
                const ctx = document.getElementById('correlation-chart');
                charts.correlation = new Chart(ctx, getCorrelationConfig());
            }

            /**
             * Initialize distribution chart
             */
            function initializeDistributionChart() {
                const ctx = document.getElementById('distribution-chart');
                charts.distribution = new Chart(ctx, getDistributionConfig());
            }

            /**
             * Initialize regression chart
             */
            function initializeRegressionChart() {
                const ctx = document.getElementById('regression-chart');
                charts.regression = new Chart(ctx, getRegressionConfig());
            }

            /**
             * Initialize effect chart
             */
            function initializeEffectChart() {
                const ctx = document.getElementById('effect-chart');
                charts.effect = new Chart(ctx, getEffectConfig());
            }

            /**
             * Update chart views
             */
            function updateSignificanceView(view) {
                currentViews.significance = view;
                charts.significance.destroy();
                charts.significance = new Chart(
                    document.getElementById('significance-chart'),
                    getSignificanceConfig()
                );
            }

            function updateCorrelationView(view) {
                currentViews.correlation = view;
                charts.correlation.destroy();
                charts.correlation = new Chart(
                    document.getElementById('correlation-chart'),
                    getCorrelationConfig()
                );
            }

            function updateDistributionView(view) {
                currentViews.distribution = view;
                charts.distribution.destroy();
                charts.distribution = new Chart(
                    document.getElementById('distribution-chart'),
                    getDistributionConfig()
                );
            }

            function updateRegressionView(view) {
                currentViews.regression = view;
                charts.regression.destroy();
                charts.regression = new Chart(
                    document.getElementById('regression-chart'),
                    getRegressionConfig()
                );
            }

            function updateEffectView(view) {
                currentViews.effect = view;
                charts.effect.destroy();
                charts.effect = new Chart(
                    document.getElementById('effect-chart'),
                    getEffectConfig()
                );
            }

            /**
             * Get chart configurations
             */
            function getSignificanceConfig() {
                // Configuration based on current view
                switch (currentViews.significance) {
                    case 'heatmap':
                        return getSignificanceHeatmapConfig();
                    case 'network':
                        return getSignificanceNetworkConfig();
                    case 'tree':
                        return getSignificanceTreeConfig();
                    default:
                        return getSignificanceHeatmapConfig();
                }
            }

            function getCorrelationConfig() {
                // Configuration based on current view
                switch (currentViews.correlation) {
                    case 'matrix':
                        return getCorrelationMatrixConfig();
                    case 'force':
                        return getCorrelationForceConfig();
                    case 'chord':
                        return getCorrelationChordConfig();
                    default:
                        return getCorrelationMatrixConfig();
                }
            }

            function getDistributionConfig() {
                // Configuration based on current view
                switch (currentViews.distribution) {
                    case 'violin':
                        return getViolinPlotConfig();
                    case 'box':
                        return getBoxPlotConfig();
                    case 'histogram':
                        return getHistogramConfig();
                    default:
                        return getViolinPlotConfig();
                }
            }

            function getRegressionConfig() {
                // Configuration based on current view
                switch (currentViews.regression) {
                    case 'line':
                        return getRegressionLineConfig();
                    case 'scatter':
                        return getRegressionScatterConfig();
                    case 'residuals':
                        return getRegressionResidualsConfig();
                    default:
                        return getRegressionLineConfig();
                }
            }

            function getEffectConfig() {
                // Configuration based on current view
                switch (currentViews.effect) {
                    case 'bar':
                        return getEffectBarConfig();
                    case 'radar':
                        return getEffectRadarConfig();
                    case 'bubble':
                        return getEffectBubbleConfig();
                    default:
                        return getEffectBarConfig();
                }
            }

            /**
             * Add interactive features
             */
            function addInteractiveFeatures() {
                // Add tooltips
                addTooltips();

                // Add zoom functionality
                addZoomFeatures();

                // Add hover effects
                addHoverEffects();

                // Add click handlers
                addClickHandlers();
            }

            /**
             * Utility functions
             */
            function addTooltips() {
                // Implementation
            }

            function addZoomFeatures() {
                // Implementation
            }

            function addHoverEffects() {
                // Implementation
            }

            function addClickHandlers() {
                // Implementation
            }
        `;

        fs.writeFileSync(path.join(this.outputDir, 'visualization.js'), script);
    }

    /**
     * Generate data files
     */
    async generateDataFiles(statistics, labels) {
        // Save statistics data
        fs.writeFileSync(
            path.join(this.outputDir, 'statistics-data.json'),
            JSON.stringify(statistics, null, 2)
        );

        // Save labels data
        fs.writeFileSync(
            path.join(this.outputDir, 'labels-data.json'),
            JSON.stringify(labels, null, 2)
        );
    }
}

module.exports = InteractiveVisualizer;
