const tf = require('@tensorflow/tfjs-node');
const stats = require('simple-statistics');

/**
 * Model evaluation utilities
 */
class ModelEvaluator {
    constructor() {
        this.metrics = {
            mse: tf.metrics.meanSquaredError,
            mae: tf.metrics.meanAbsoluteError,
            rmse: (y_true, y_pred) => tf.sqrt(tf.metrics.meanSquaredError(y_true, y_pred))
        };
    }

    /**
     * Evaluate model performance
     */
    async evaluateModel(model, testData, options = {}) {
        const evaluation = {
            accuracy: await this.evaluateAccuracy(model, testData),
            metrics: await this.calculateMetrics(model, testData),
            validation: await this.crossValidate(model, testData, options),
            stability: this.assessModelStability(model, testData),
            reliability: this.assessReliability(model, testData)
        };

        return {
            ...evaluation,
            recommendations: this.generateOptimizationRecommendations(evaluation)
        };
    }

    /**
     * Evaluate prediction accuracy
     */
    async evaluateAccuracy(model, testData) {
        const { X, y } = testData;
        const predictions = model.predict(X);
        const errors = this.calculateErrors(y, predictions);

        return {
            overall: this.calculateOverallAccuracy(errors),
            byRange: this.calculateRangeAccuracy(y, predictions),
            confidence: this.calculateConfidenceIntervals(errors)
        };
    }

    /**
     * Calculate model metrics
     */
    async calculateMetrics(model, testData) {
        const { X, y } = testData;
        const predictions = model.predict(X);

        const results = {};
        for (const [name, metric] of Object.entries(this.metrics)) {
            results[name] = await this.calculateMetric(metric, y, predictions);
        }

        return {
            ...results,
            r2: this.calculateR2Score(y, predictions),
            adjustedR2: this.calculateAdjustedR2(y, predictions, model)
        };
    }

    /**
     * Perform cross validation
     */
    async crossValidate(model, data, options = {}) {
        const {
            folds = 5,
            shuffle = true,
            metrics = ['mse', 'mae']
        } = options;

        const results = await this.kFoldCrossValidation(model, data, folds, shuffle);
        const scores = this.calculateCrossValidationScores(results, metrics);

        return {
            scores,
            stability: this.assessCrossValidationStability(scores),
            significance: this.calculateStatisticalSignificance(scores)
        };
    }

    /**
     * Perform k-fold cross validation
     */
    async kFoldCrossValidation(model, data, folds, shuffle) {
        const foldResults = [];
        const foldSize = Math.floor(data.X.shape[0] / folds);

        // Generate fold indices
        const indices = Array.from({ length: data.X.shape[0] }, (_, i) => i);
        if (shuffle) {
            this.shuffleArray(indices);
        }

        // Perform k-fold validation
        for (let i = 0; i < folds; i++) {
            const testIndices = indices.slice(i * foldSize, (i + 1) * foldSize);
            const trainIndices = indices.filter(idx => !testIndices.includes(idx));

            const trainData = this.selectByIndices(data, trainIndices);
            const testData = this.selectByIndices(data, testIndices);

            // Train model on fold
            await model.fit(trainData.X, trainData.y, {
                epochs: 50,
                batchSize: 32,
                verbose: 0
            });

            // Evaluate on test data
            const predictions = model.predict(testData.X);
            const metrics = await this.calculateMetrics(model, testData);

            foldResults.push({
                fold: i + 1,
                metrics,
                predictions: predictions.arraySync()
            });
        }

        return foldResults;
    }

    /**
     * Calculate cross validation scores
     */
    calculateCrossValidationScores(results, metrics) {
        const scores = {};

        for (const metric of metrics) {
            const values = results.map(r => r.metrics[metric]);
            scores[metric] = {
                mean: stats.mean(values),
                std: stats.standardDeviation(values),
                min: Math.min(...values),
                max: Math.max(...values)
            };
        }

        return scores;
    }

    /**
     * Assess model stability
     */
    assessModelStability(model, testData) {
        const predictions = [];
        const runs = 10;

        // Generate multiple predictions
        for (let i = 0; i < runs; i++) {
            predictions.push(model.predict(testData.X).arraySync());
        }

        return {
            variability: this.calculatePredictionVariability(predictions),
            consistency: this.assessPredictionConsistency(predictions),
            reliability: this.calculateStabilityScore(predictions)
        };
    }

    /**
     * Calculate prediction variability
     */
    calculatePredictionVariability(predictions) {
        const variances = [];

        for (let i = 0; i < predictions[0].length; i++) {
            const values = predictions.map(p => p[i]);
            variances.push(stats.variance(values));
        }

        return {
            mean: stats.mean(variances),
            max: Math.max(...variances),
            std: stats.standardDeviation(variances)
        };
    }

    /**
     * Assess prediction consistency
     */
    assessPredictionConsistency(predictions) {
        const correlations = [];

        // Calculate correlations between prediction runs
        for (let i = 0; i < predictions.length; i++) {
            for (let j = i + 1; j < predictions.length; j++) {
                correlations.push(
                    stats.sampleCorrelation(predictions[i], predictions[j])
                );
            }
        }

        return {
            meanCorrelation: stats.mean(correlations),
            minCorrelation: Math.min(...correlations),
            consistency: this.calculateConsistencyScore(correlations)
        };
    }

    /**
     * Assess model reliability
     */
    assessReliability(model, testData) {
        const predictions = model.predict(testData.X);
        const errors = this.calculateErrors(testData.y, predictions);

        return {
            score: this.calculateReliabilityScore(errors),
            confidence: this.calculateConfidenceScore(errors),
            factors: this.analyzeReliabilityFactors(model, testData)
        };
    }

    /**
     * Calculate errors
     */
    calculateErrors(actual, predicted) {
        const actualArray = actual.arraySync();
        const predictedArray = predicted.arraySync();

        return actualArray.map((a, i) => Math.abs(a - predictedArray[i]));
    }

    /**
     * Calculate overall accuracy
     */
    calculateOverallAccuracy(errors) {
        const mse = stats.mean(errors.map(e => e * e));
        const rmse = Math.sqrt(mse);
        const mae = stats.mean(errors);

        return {
            mse,
            rmse,
            mae,
            score: 1 / (1 + rmse) // Normalize to 0-1 range
        };
    }

    /**
     * Calculate range accuracy
     */
    calculateRangeAccuracy(actual, predicted) {
        const actualArray = actual.arraySync();
        const predictedArray = predicted.arraySync();
        const ranges = this.defineValueRanges(actualArray);

        const rangeAccuracies = {};
        for (const [range, indices] of Object.entries(ranges)) {
            const rangeErrors = indices.map(i =>
                Math.abs(actualArray[i] - predictedArray[i])
            );
            rangeAccuracies[range] = this.calculateOverallAccuracy(rangeErrors);
        }

        return rangeAccuracies;
    }

    /**
     * Calculate confidence intervals
     */
    calculateConfidenceIntervals(errors) {
        const mean = stats.mean(errors);
        const std = stats.standardDeviation(errors);
        const n = errors.length;

        const marginOfError = 1.96 * (std / Math.sqrt(n)); // 95% confidence

        return {
            mean,
            lower: mean - marginOfError,
            upper: mean + marginOfError,
            confidence: 0.95
        };
    }

    /**
     * Generate optimization recommendations
     */
    generateOptimizationRecommendations(evaluation) {
        const recommendations = [];

        // Accuracy recommendations
        if (evaluation.accuracy.overall.score < 0.8) {
            recommendations.push({
                type: 'accuracy',
                priority: 'high',
                message: 'Model accuracy needs improvement',
                actions: [
                    'Increase training data',
                    'Tune hyperparameters',
                    'Consider model architecture changes'
                ]
            });
        }

        // Stability recommendations
        if (evaluation.stability.variability.mean > 0.1) {
            recommendations.push({
                type: 'stability',
                priority: 'medium',
                message: 'Model predictions show high variability',
                actions: [
                    'Add regularization',
                    'Increase training epochs',
                    'Review data preprocessing'
                ]
            });
        }

        // Reliability recommendations
        if (evaluation.reliability.score < 0.7) {
            recommendations.push({
                type: 'reliability',
                priority: 'high',
                message: 'Model reliability needs improvement',
                actions: [
                    'Implement cross-validation',
                    'Add uncertainty estimation',
                    'Monitor prediction drift'
                ]
            });
        }

        return recommendations;
    }

    /**
     * Utility functions
     */
    shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    selectByIndices(data, indices) {
        return {
            X: tf.gather(data.X, indices),
            y: tf.gather(data.y, indices)
        };
    }

    defineValueRanges(values) {
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min;
        const numRanges = 5;
        const rangeSize = range / numRanges;

        const ranges = {};
        for (let i = 0; i < numRanges; i++) {
            const start = min + i * rangeSize;
            const end = start + rangeSize;
            ranges[`${start.toFixed(2)}-${end.toFixed(2)}`] = values
                .map((v, idx) => [v, idx])
                .filter(([v]) => v >= start && v < end)
                .map(([, idx]) => idx);
        }

        return ranges;
    }
}

module.exports = ModelEvaluator;
