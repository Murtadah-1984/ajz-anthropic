const stats = require('simple-statistics');

/**
 * Statistical analysis utilities
 */
class StatisticalAnalyzer {
    /**
     * Analyze statistical differences between models
     */
    analyzeStatistics(evaluations, labels) {
        return {
            significance: this.analyzeSignificance(evaluations),
            correlations: this.analyzeCorrelations(evaluations),
            distributions: this.analyzeDistributions(evaluations),
            effectSizes: this.calculateEffectSizes(evaluations),
            regressionAnalysis: this.performRegressionAnalysis(evaluations),
            summary: this.generateStatisticalSummary(evaluations, labels)
        };
    }

    /**
     * Analyze statistical significance
     */
    analyzeSignificance(evaluations) {
        const results = {};

        // Analyze accuracy differences
        results.accuracy = this.performTTests(
            evaluations.map(e => e.accuracy.overall.score)
        );

        // Analyze performance differences
        results.performance = this.performTTests(
            evaluations.map(e => e.performance.trainingTime)
        );

        // Analyze stability differences
        results.stability = this.performTTests(
            evaluations.map(e => e.stability.consistency.meanCorrelation)
        );

        // Analyze reliability differences
        results.reliability = this.performTTests(
            evaluations.map(e => e.reliability.score)
        );

        return results;
    }

    /**
     * Analyze correlations between metrics
     */
    analyzeCorrelations(evaluations) {
        const metrics = {
            accuracy: evaluations.map(e => e.accuracy.overall.score),
            performance: evaluations.map(e => e.performance.trainingTime),
            stability: evaluations.map(e => e.stability.consistency.meanCorrelation),
            reliability: evaluations.map(e => e.reliability.score)
        };

        const correlations = {};
        const metricNames = Object.keys(metrics);

        for (let i = 0; i < metricNames.length; i++) {
            for (let j = i + 1; j < metricNames.length; j++) {
                const name1 = metricNames[i];
                const name2 = metricNames[j];
                correlations[`${name1}_${name2}`] = {
                    correlation: stats.sampleCorrelation(metrics[name1], metrics[name2]),
                    significance: this.calculateCorrelationSignificance(
                        metrics[name1],
                        metrics[name2]
                    )
                };
            }
        }

        return correlations;
    }

    /**
     * Analyze metric distributions
     */
    analyzeDistributions(evaluations) {
        const distributions = {};
        const metrics = {
            accuracy: evaluations.map(e => e.accuracy.overall.score),
            performance: evaluations.map(e => e.performance.trainingTime),
            stability: evaluations.map(e => e.stability.consistency.meanCorrelation),
            reliability: evaluations.map(e => e.reliability.score)
        };

        for (const [name, values] of Object.entries(metrics)) {
            distributions[name] = {
                normality: this.testNormality(values),
                statistics: this.calculateDistributionStatistics(values),
                outliers: this.detectOutliers(values)
            };
        }

        return distributions;
    }

    /**
     * Calculate effect sizes
     */
    calculateEffectSizes(evaluations) {
        const effectSizes = {};
        const metrics = {
            accuracy: evaluations.map(e => e.accuracy.overall.score),
            performance: evaluations.map(e => e.performance.trainingTime),
            stability: evaluations.map(e => e.stability.consistency.meanCorrelation),
            reliability: evaluations.map(e => e.reliability.score)
        };

        for (const [name, values] of Object.entries(metrics)) {
            effectSizes[name] = {
                cohensD: this.calculateCohensD(values),
                hedgesG: this.calculateHedgesG(values),
                glasssDelta: this.calculateGlassDelta(values)
            };
        }

        return effectSizes;
    }

    /**
     * Perform regression analysis
     */
    performRegressionAnalysis(evaluations) {
        const analysis = {};
        const metrics = {
            accuracy: evaluations.map(e => e.accuracy.overall.score),
            performance: evaluations.map(e => e.performance.trainingTime),
            stability: evaluations.map(e => e.stability.consistency.meanCorrelation),
            reliability: evaluations.map(e => e.reliability.score)
        };

        // Generate time series for regression
        const timePoints = Array.from(
            { length: evaluations.length },
            (_, i) => i
        );

        for (const [name, values] of Object.entries(metrics)) {
            analysis[name] = {
                regression: this.performLinearRegression(timePoints, values),
                trend: this.analyzeTrend(timePoints, values),
                forecast: this.generateForecast(timePoints, values)
            };
        }

        return analysis;
    }

    /**
     * Perform t-tests between model versions
     */
    performTTests(values) {
        const results = [];

        for (let i = 0; i < values.length - 1; i++) {
            for (let j = i + 1; j < values.length; j++) {
                const tTestResult = this.tTest(
                    [values[i]],
                    [values[j]]
                );

                results.push({
                    comparison: `${i}_vs_${j}`,
                    tValue: tTestResult.statistic,
                    pValue: tTestResult.pValue,
                    significant: tTestResult.pValue < 0.05,
                    confidenceInterval: tTestResult.confidenceInterval
                });
            }
        }

        return results;
    }

    /**
     * Calculate correlation significance
     */
    calculateCorrelationSignificance(x, y) {
        const correlation = stats.sampleCorrelation(x, y);
        const n = x.length;
        const t = correlation * Math.sqrt((n - 2) / (1 - correlation * correlation));
        const pValue = 2 * (1 - stats.tDistribution(n - 2)(Math.abs(t)));

        return {
            tStatistic: t,
            pValue,
            significant: pValue < 0.05
        };
    }

    /**
     * Test normality of distribution
     */
    testNormality(values) {
        // Shapiro-Wilk test implementation
        const n = values.length;
        const sortedValues = [...values].sort((a, b) => a - b);

        // Calculate mean and variance
        const mean = stats.mean(values);
        const variance = stats.variance(values);

        // Calculate test statistic
        let w = 0;
        let s2 = 0;

        for (let i = 0; i < Math.floor(n / 2); i++) {
            const weight = this.getShapiroWeight(n, i);
            w += weight * (sortedValues[n - 1 - i] - sortedValues[i]);
        }

        for (let i = 0; i < n; i++) {
            s2 += Math.pow(sortedValues[i] - mean, 2);
        }

        const W = (w * w) / s2;
        const pValue = this.calculateShapiroPValue(W, n);

        return {
            statistic: W,
            pValue,
            isNormal: pValue > 0.05
        };
    }

    /**
     * Calculate distribution statistics
     */
    calculateDistributionStatistics(values) {
        return {
            mean: stats.mean(values),
            median: stats.median(values),
            mode: stats.mode(values),
            variance: stats.variance(values),
            standardDeviation: stats.standardDeviation(values),
            skewness: stats.sampleSkewness(values),
            kurtosis: stats.sampleKurtosis(values),
            quantiles: {
                q1: stats.quantile(values, 0.25),
                q2: stats.quantile(values, 0.5),
                q3: stats.quantile(values, 0.75)
            }
        };
    }

    /**
     * Detect outliers using IQR method
     */
    detectOutliers(values) {
        const q1 = stats.quantile(values, 0.25);
        const q3 = stats.quantile(values, 0.75);
        const iqr = q3 - q1;
        const lowerBound = q1 - 1.5 * iqr;
        const upperBound = q3 + 1.5 * iqr;

        const outliers = values.map((value, index) => ({
            index,
            value,
            isOutlier: value < lowerBound || value > upperBound
        })).filter(item => item.isOutlier);

        return {
            bounds: { lower: lowerBound, upper: upperBound },
            outliers
        };
    }

    /**
     * Calculate Cohen's d effect size
     */
    calculateCohensD(values) {
        if (values.length < 2) return null;

        const group1 = values.slice(0, Math.floor(values.length / 2));
        const group2 = values.slice(Math.floor(values.length / 2));

        const mean1 = stats.mean(group1);
        const mean2 = stats.mean(group2);
        const pooledStd = Math.sqrt(
            ((group1.length - 1) * stats.variance(group1) +
             (group2.length - 1) * stats.variance(group2)) /
            (group1.length + group2.length - 2)
        );

        const d = Math.abs(mean1 - mean2) / pooledStd;

        return {
            value: d,
            interpretation: this.interpretEffectSize(d)
        };
    }

    /**
     * Perform linear regression
     */
    performLinearRegression(x, y) {
        const regression = stats.linearRegression(
            x.map((xi, i) => [xi, y[i]])
        );

        const predictions = x.map(xi => regression.m * xi + regression.b);
        const residuals = y.map((yi, i) => yi - predictions[i]);
        const rSquared = 1 - stats.sum(residuals.map(r => r * r)) /
            stats.sum(y.map(yi => yi - stats.mean(y)).map(d => d * d));

        return {
            slope: regression.m,
            intercept: regression.b,
            rSquared,
            predictions,
            residuals
        };
    }

    /**
     * Generate statistical summary
     */
    generateStatisticalSummary(evaluations, labels) {
        return {
            overview: this.generateOverview(evaluations, labels),
            significantFindings: this.findSignificantDifferences(evaluations, labels),
            trends: this.identifyTrends(evaluations, labels),
            recommendations: this.generateStatisticalRecommendations(evaluations)
        };
    }

    /**
     * Utility functions
     */
    tTest(group1, group2) {
        const mean1 = stats.mean(group1);
        const mean2 = stats.mean(group2);
        const var1 = stats.variance(group1);
        const var2 = stats.variance(group2);
        const n1 = group1.length;
        const n2 = group2.length;

        const pooledVar = ((n1 - 1) * var1 + (n2 - 1) * var2) / (n1 + n2 - 2);
        const statistic = (mean1 - mean2) / Math.sqrt(pooledVar * (1/n1 + 1/n2));
        const df = n1 + n2 - 2;
        const pValue = 2 * (1 - stats.tDistribution(df)(Math.abs(statistic)));

        return {
            statistic,
            pValue,
            degreesOfFreedom: df,
            confidenceInterval: this.calculateConfidenceInterval(mean1, mean2, pooledVar, n1, n2)
        };
    }

    calculateConfidenceInterval(mean1, mean2, pooledVar, n1, n2) {
        const diff = mean1 - mean2;
        const se = Math.sqrt(pooledVar * (1/n1 + 1/n2));
        const t = stats.tDistribution(n1 + n2 - 2)(0.975);

        return {
            lower: diff - t * se,
            upper: diff + t * se
        };
    }

    interpretEffectSize(d) {
        if (d < 0.2) return 'negligible';
        if (d < 0.5) return 'small';
        if (d < 0.8) return 'medium';
        return 'large';
    }

    getShapiroWeight(n, i) {
        // Approximation of Shapiro-Wilk weights
        const m = stats.mean([0, 1]);
        const c = stats.variance([0, 1]);
        return stats.normalDistribution(m, c)((i + 1) / (n + 1));
    }

    calculateShapiroPValue(W, n) {
        // Approximation of Shapiro-Wilk p-value
        const y = Math.log(1 - W);
        const mu = -1.2725 + 1.0521 * Math.pow(-Math.log(n), 0.01815);
        const sigma = 1.0308 - 0.26758 * Math.pow(Math.log(n), 0.4803);
        const z = (y - mu) / sigma;
        return 1 - stats.normalDistribution(0, 1)(z);
    }
}

module.exports = StatisticalAnalyzer;
