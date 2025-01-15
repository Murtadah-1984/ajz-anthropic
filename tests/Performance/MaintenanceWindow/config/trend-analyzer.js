const stats = require('simple-statistics');

/**
 * Performance trend analysis utilities
 */
class TrendAnalyzer {
    /**
     * Analyze performance trends
     */
    analyzeTrends(results) {
        return {
            executionTrends: this.analyzeExecutionTrends(results),
            memoryTrends: this.analyzeMemoryTrends(results),
            concurrencyTrends: this.analyzeConcurrencyTrends(results),
            resourceTrends: this.analyzeResourceTrends(results),
            anomalies: this.detectAnomalies(results),
            patterns: this.detectPatterns(results),
            recommendations: this.generateRecommendations(results)
        };
    }

    /**
     * Analyze execution time trends
     */
    analyzeExecutionTrends(results) {
        const times = results.validationTimes;
        const trend = this.calculateTrend(times);
        const volatility = this.calculateVolatility(times);
        const seasonality = this.detectSeasonality(times);

        return {
            trend,
            volatility,
            seasonality,
            statistics: {
                mean: stats.mean(times),
                median: stats.median(times),
                stdDev: stats.standardDeviation(times),
                percentiles: {
                    p50: stats.quantile(times, 0.5),
                    p75: stats.quantile(times, 0.75),
                    p90: stats.quantile(times, 0.9),
                    p95: stats.quantile(times, 0.95),
                    p99: stats.quantile(times, 0.99)
                }
            },
            stability: this.assessStability(times)
        };
    }

    /**
     * Analyze memory usage trends
     */
    analyzeMemoryTrends(results) {
        const heapUsed = results.memoryUsage.map(m => m.heapUsed / 1024 / 1024);
        const heapTotal = results.memoryUsage.map(m => m.heapTotal / 1024 / 1024);

        return {
            heapUsed: {
                trend: this.calculateTrend(heapUsed),
                growth: this.calculateGrowthRate(heapUsed),
                leakProbability: this.assessMemoryLeak(heapUsed)
            },
            heapTotal: {
                trend: this.calculateTrend(heapTotal),
                growth: this.calculateGrowthRate(heapTotal)
            },
            fragmentation: this.calculateFragmentation(results.memoryUsage),
            garbageCollection: this.analyzeGCImpact(results)
        };
    }

    /**
     * Analyze concurrency trends
     */
    analyzeConcurrencyTrends(results) {
        const concurrencyData = results.concurrencyResults;
        const scalingFactor = this.calculateScalingFactor(concurrencyData);
        const bottlenecks = this.identifyBottlenecks(concurrencyData);

        return {
            scalingFactor,
            bottlenecks,
            efficiency: this.calculateConcurrencyEfficiency(concurrencyData),
            saturationPoint: this.findSaturationPoint(concurrencyData),
            recommendations: this.getConcurrencyRecommendations(concurrencyData)
        };
    }

    /**
     * Analyze resource usage trends
     */
    analyzeResourceTrends(results) {
        const cpu = results.resourceUsage.map(r => r.cpu);
        const memory = results.resourceUsage.map(r => r.memory);

        return {
            cpu: {
                trend: this.calculateTrend(cpu),
                saturation: this.calculateResourceSaturation(cpu),
                bottlenecks: this.identifyResourceBottlenecks(cpu, 'cpu')
            },
            memory: {
                trend: this.calculateTrend(memory),
                saturation: this.calculateResourceSaturation(memory),
                bottlenecks: this.identifyResourceBottlenecks(memory, 'memory')
            },
            correlation: this.calculateResourceCorrelation(cpu, memory),
            efficiency: this.calculateResourceEfficiency(results.resourceUsage)
        };
    }

    /**
     * Calculate trend
     */
    calculateTrend(data) {
        const x = Array.from({ length: data.length }, (_, i) => i);
        const regression = stats.linearRegression(x.map(i => [i, data[i]]));
        const correlation = stats.sampleCorrelation(x, data);

        return {
            slope: regression.m,
            intercept: regression.b,
            correlation,
            direction: regression.m > 0 ? 'increasing' : 'decreasing',
            strength: Math.abs(correlation) > 0.7 ? 'strong' : 'weak'
        };
    }

    /**
     * Calculate volatility
     */
    calculateVolatility(data) {
        const returns = [];
        for (let i = 1; i < data.length; i++) {
            returns.push((data[i] - data[i - 1]) / data[i - 1]);
        }

        return {
            value: stats.standardDeviation(returns),
            annualized: stats.standardDeviation(returns) * Math.sqrt(data.length),
            stability: this.assessVolatilityStability(returns)
        };
    }

    /**
     * Detect seasonality
     */
    detectSeasonality(data) {
        const patterns = [];
        const maxPeriod = Math.floor(data.length / 2);

        // Test different periods
        for (let period = 2; period <= maxPeriod; period++) {
            const correlation = this.calculateSeasonalCorrelation(data, period);
            if (correlation > 0.7) {
                patterns.push({ period, correlation });
            }
        }

        return {
            patterns: patterns.sort((a, b) => b.correlation - a.correlation),
            hasSeasonality: patterns.length > 0,
            dominantPeriod: patterns[0]?.period
        };
    }

    /**
     * Calculate seasonal correlation
     */
    calculateSeasonalCorrelation(data, period) {
        const segments = [];
        for (let i = 0; i < data.length - period; i += period) {
            segments.push(data.slice(i, i + period));
        }

        if (segments.length < 2) return 0;

        const correlations = [];
        for (let i = 1; i < segments.length; i++) {
            correlations.push(
                stats.sampleCorrelation(segments[i - 1], segments[i])
            );
        }

        return stats.mean(correlations);
    }

    /**
     * Calculate growth rate
     */
    calculateGrowthRate(data) {
        const growthRates = [];
        for (let i = 1; i < data.length; i++) {
            growthRates.push((data[i] - data[i - 1]) / data[i - 1]);
        }

        return {
            average: stats.mean(growthRates),
            trend: this.calculateTrend(growthRates),
            consistency: this.assessGrowthConsistency(growthRates)
        };
    }

    /**
     * Assess memory leak probability
     */
    assessMemoryLeak(heapUsed) {
        const growth = this.calculateGrowthRate(heapUsed);
        const trend = this.calculateTrend(heapUsed);

        // Calculate leak score based on multiple factors
        const leakScore = (
            (growth.average > 0 ? 0.4 : 0) +
            (trend.correlation > 0.7 ? 0.3 : 0) +
            (growth.consistency.isConsistent ? 0.3 : 0)
        );

        return {
            score: leakScore,
            probability: leakScore > 0.7 ? 'high' : leakScore > 0.3 ? 'medium' : 'low',
            confidence: this.calculateConfidence(heapUsed.length)
        };
    }

    /**
     * Calculate fragmentation
     */
    calculateFragmentation(memoryUsage) {
        const fragmentationRatios = memoryUsage.map(m =>
            1 - (m.heapUsed / m.heapTotal)
        );

        return {
            current: fragmentationRatios[fragmentationRatios.length - 1],
            average: stats.mean(fragmentationRatios),
            trend: this.calculateTrend(fragmentationRatios),
            severity: this.assessFragmentationSeverity(fragmentationRatios)
        };
    }

    /**
     * Analyze garbage collection impact
     */
    analyzeGCImpact(results) {
        const heapUsed = results.memoryUsage.map(m => m.heapUsed);
        const drops = [];

        // Detect significant memory drops (potential GC events)
        for (let i = 1; i < heapUsed.length; i++) {
            const drop = heapUsed[i - 1] - heapUsed[i];
            if (drop > 0 && drop / heapUsed[i - 1] > 0.1) { // 10% threshold
                drops.push({
                    index: i,
                    size: drop,
                    percentage: (drop / heapUsed[i - 1]) * 100
                });
            }
        }

        return {
            events: drops,
            frequency: drops.length / heapUsed.length,
            impact: this.assessGCImpact(drops, heapUsed)
        };
    }

    /**
     * Calculate scaling factor
     */
    calculateScalingFactor(concurrencyData) {
        const x = concurrencyData.map(d => d.concurrent);
        const y = concurrencyData.map(d => d.avgDuration);
        const regression = stats.linearRegression(x.map((v, i) => [v, y[i]]));

        return {
            factor: regression.m,
            efficiency: 1 / regression.m,
            linearity: stats.sampleCorrelation(x, y)
        };
    }

    /**
     * Identify bottlenecks
     */
    identifyBottlenecks(concurrencyData) {
        const bottlenecks = [];
        let previousEfficiency = Infinity;

        concurrencyData.forEach((data, i) => {
            if (i === 0) return;

            const efficiency = data.concurrent / data.avgDuration;
            const efficiencyDrop = (previousEfficiency - efficiency) / previousEfficiency;

            if (efficiencyDrop > 0.2) { // 20% efficiency drop threshold
                bottlenecks.push({
                    concurrency: data.concurrent,
                    efficiencyDrop: efficiencyDrop * 100,
                    avgDuration: data.avgDuration
                });
            }

            previousEfficiency = efficiency;
        });

        return {
            points: bottlenecks,
            recommendations: this.getBottleneckRecommendations(bottlenecks)
        };
    }

    /**
     * Calculate concurrency efficiency
     */
    calculateConcurrencyEfficiency(concurrencyData) {
        const efficiencies = concurrencyData.map(data => ({
            concurrent: data.concurrent,
            efficiency: data.concurrent / data.avgDuration,
            overhead: (data.maxDuration - data.avgDuration) / data.avgDuration
        }));

        return {
            data: efficiencies,
            optimal: this.findOptimalConcurrency(efficiencies),
            trend: this.calculateTrend(efficiencies.map(e => e.efficiency))
        };
    }

    /**
     * Find saturation point
     */
    findSaturationPoint(concurrencyData) {
        const efficiencies = this.calculateConcurrencyEfficiency(concurrencyData).data;
        let maxEfficiency = -Infinity;
        let saturationPoint = null;

        efficiencies.forEach((data, i) => {
            if (data.efficiency > maxEfficiency) {
                maxEfficiency = data.efficiency;
            } else if (!saturationPoint && data.efficiency < maxEfficiency * 0.8) {
                saturationPoint = concurrencyData[i - 1];
            }
        });

        return {
            point: saturationPoint,
            efficiency: maxEfficiency,
            recommendation: this.getSaturationRecommendation(saturationPoint)
        };
    }

    /**
     * Generate recommendations
     */
    generateRecommendations(results) {
        const recommendations = [];

        // Analyze execution time trends
        const execTrends = this.analyzeExecutionTrends(results);
        if (execTrends.trend.direction === 'increasing' && execTrends.trend.strength === 'strong') {
            recommendations.push({
                type: 'performance_degradation',
                priority: 'high',
                message: 'Performance is consistently degrading over time',
                actions: [
                    'Review recent code changes',
                    'Check for resource constraints',
                    'Consider optimization opportunities'
                ]
            });
        }

        // Analyze memory trends
        const memTrends = this.analyzeMemoryTrends(results);
        if (memTrends.heapUsed.leakProbability.score > 0.7) {
            recommendations.push({
                type: 'memory_leak',
                priority: 'high',
                message: 'Potential memory leak detected',
                actions: [
                    'Review memory allocation patterns',
                    'Check for unclosed resources',
                    'Consider implementing memory monitoring'
                ]
            });
        }

        // Analyze concurrency trends
        const concTrends = this.analyzeConcurrencyTrends(results);
        if (concTrends.bottlenecks.points.length > 0) {
            recommendations.push({
                type: 'concurrency_bottleneck',
                priority: 'medium',
                message: 'Concurrency bottlenecks detected',
                actions: [
                    'Review resource utilization',
                    'Consider increasing available resources',
                    'Optimize concurrent operations'
                ]
            });
        }

        return recommendations;
    }

    /**
     * Calculate confidence
     */
    calculateConfidence(sampleSize) {
        // More samples = higher confidence
        const sampleConfidence = Math.min(sampleSize / 100, 1);

        // Confidence increases with sample size but plateaus
        return {
            score: sampleConfidence,
            level: sampleConfidence > 0.8 ? 'high' : sampleConfidence > 0.5 ? 'medium' : 'low'
        };
    }
}

module.exports = TrendAnalyzer;
