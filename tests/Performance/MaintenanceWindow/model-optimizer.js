const tf = require('@tensorflow/tfjs-node');
const hyperparameters = require('./config/hyperparameters');

/**
 * Model optimization utilities
 */
class ModelOptimizer {
    constructor(mlAnalyzer) {
        this.analyzer = mlAnalyzer;
        this.hyperparameters = hyperparameters;
        this.optimizationHistory = new Map();
    }

    /**
     * Optimize model hyperparameters
     */
    async optimizeModel(type, name, timeRange = '30d') {
        const data = await this.analyzer.store.getTimeSeries(type, name, '1h', timeRange);
        const values = data.map(d => d.average);

        // Split data for optimization
        const splitIndex = Math.floor(values.length * 0.8);
        const trainData = values.slice(0, splitIndex);
        const validData = values.slice(splitIndex);

        // Grid search for optimal parameters
        const results = await this.gridSearch(trainData, validData);

        // Update model with optimal parameters
        const optimalParams = results.optimal;
        await this.updateModel(type, name, optimalParams);

        // Store optimization history
        this.optimizationHistory.set(`${type}:${name}`, {
            timestamp: new Date().toISOString(),
            results,
            improvement: results.improvement
        });

        return results;
    }

    /**
     * Perform grid search for optimal parameters
     */
    async gridSearch(trainData, validData) {
        const results = [];
        const baselineLoss = await this.evaluateModel(
            trainData,
            validData,
            this.hyperparameters.default
        );

        for (const units of this.hyperparameters.lstm.units) {
            for (const learningRate of this.hyperparameters.optimizer.learningRate) {
                for (const batchSize of this.hyperparameters.training.batchSize) {
                    const params = {
                        lstm: { units },
                        optimizer: { learningRate },
                        training: { batchSize }
                    };

                    const loss = await this.evaluateModel(trainData, validData, params);
                    results.push({ params, loss });
                }
            }
        }

        // Find optimal parameters
        results.sort((a, b) => a.loss - b.loss);
        const optimal = results[0].params;
        const improvement = ((baselineLoss - results[0].loss) / baselineLoss) * 100;

        return {
            optimal,
            improvement,
            allResults: results
        };
    }

    /**
     * Evaluate model with given parameters
     */
    async evaluateModel(trainData, validData, params) {
        const model = this.createOptimizedModel(params);
        const { X: trainX, y: trainY } = this.analyzer.prepareData(trainData);
        const { X: validX, y: validY } = this.analyzer.prepareData(validData);

        await model.fit(
            trainX.reshape([trainX.shape[0], trainX.shape[1], 1]),
            trainY,
            {
                epochs: params.training.epochs || 50,
                batchSize: params.training.batchSize,
                validationData: [
                    validX.reshape([validX.shape[0], validX.shape[1], 1]),
                    validY
                ],
                verbose: 0
            }
        );

        const loss = model.evaluate(
            validX.reshape([validX.shape[0], validX.shape[1], 1]),
            validY
        ).dataSync()[0];

        return loss;
    }

    /**
     * Create optimized model
     */
    createOptimizedModel(params) {
        const model = tf.sequential();

        // Optimized LSTM layers
        model.add(tf.layers.lstm({
            units: params.lstm.units,
            inputShape: [this.analyzer.windowSize, 1],
            returnSequences: true,
            kernelRegularizer: tf.regularizers.l2({ l2: 0.01 })
        }));

        model.add(tf.layers.batchNormalization());
        model.add(tf.layers.dropout({ rate: 0.3 }));

        model.add(tf.layers.lstm({
            units: params.lstm.units / 2,
            returnSequences: false,
            kernelRegularizer: tf.regularizers.l2({ l2: 0.01 })
        }));

        model.add(tf.layers.batchNormalization());
        model.add(tf.layers.dropout({ rate: 0.2 }));

        model.add(tf.layers.dense({
            units: 1,
            activation: 'linear'
        }));

        // Optimized compilation
        model.compile({
            optimizer: tf.train.adam(params.optimizer.learningRate),
            loss: 'meanSquaredError',
            metrics: ['mse', 'mae']
        });

        return model;
    }

    /**
     * Update existing model with optimal parameters
     */
    async updateModel(type, name, params) {
        const optimizedModel = this.createOptimizedModel(params);
        const data = await this.analyzer.store.getTimeSeries(type, name, '30d', '1h');
        const values = data.map(d => d.average);

        // Normalize data
        const min = Math.min(...values);
        const max = Math.max(...values);
        const normalizedValues = values.map(v => (v - min) / (max - min));

        // Train optimized model
        const { X, y } = this.analyzer.prepareData(normalizedValues);
        await optimizedModel.fit(
            X.reshape([X.shape[0], X.shape[1], 1]),
            y,
            {
                epochs: params.training.epochs || 50,
                batchSize: params.training.batchSize,
                validationSplit: 0.2,
                verbose: 0
            }
        );

        // Update model in analyzer
        this.analyzer.models.set(`${type}:${name}`, {
            model: optimizedModel,
            min,
            max,
            params
        });
    }

    /**
     * Analyze model performance
     */
    async analyzeModelPerformance(type, name) {
        const modelInfo = this.analyzer.models.get(`${type}:${name}`);
        if (!modelInfo) return null;

        const data = await this.analyzer.store.getTimeSeries(type, name, '7d', '1h');
        const values = data.map(d => d.average);
        const { X, y } = this.analyzer.prepareData(values);

        // Calculate metrics
        const predictions = modelInfo.model.predict(
            X.reshape([X.shape[0], X.shape[1], 1])
        ).dataSync();

        const metrics = {
            mse: tf.metrics.meanSquaredError(y, predictions).dataSync()[0],
            mae: tf.metrics.meanAbsoluteError(y, predictions).dataSync()[0],
            rmse: Math.sqrt(tf.metrics.meanSquaredError(y, predictions).dataSync()[0])
        };

        // Calculate feature importance
        const importance = await this.calculateFeatureImportance(
            modelInfo.model,
            X.reshape([X.shape[0], X.shape[1], 1]),
            y
        );

        return {
            metrics,
            importance,
            optimization: this.optimizationHistory.get(`${type}:${name}`),
            parameters: modelInfo.params
        };
    }

    /**
     * Calculate feature importance
     */
    async calculateFeatureImportance(model, X, y) {
        const baselineLoss = model.evaluate(X, y).dataSync()[0];
        const importance = [];

        // Calculate importance for each time step
        for (let i = 0; i < X.shape[1]; i++) {
            const perturbedX = X.clone();
            const values = perturbedX.slice([0, i, 0], [-1, 1, -1]).dataSync();
            const noise = tf.randomNormal(values.shape, 0, tf.moments(values).variance.dataSync()[0]);

            perturbedX.slice([0, i, 0], [-1, 1, -1]).assign(noise);
            const perturbedLoss = model.evaluate(perturbedX, y).dataSync()[0];

            importance.push({
                timeStep: i,
                importance: (perturbedLoss - baselineLoss) / baselineLoss
            });
        }

        return importance.sort((a, b) => b.importance - a.importance);
    }

    /**
     * Get optimization recommendations
     */
    getOptimizationRecommendations(performance) {
        const recommendations = [];

        // Analyze metrics
        if (performance.metrics.rmse > 0.2) {
            recommendations.push({
                type: 'model_complexity',
                message: 'Consider increasing model complexity by adding more LSTM units',
                priority: 'high'
            });
        }

        // Analyze feature importance
        const importantFeatures = performance.importance
            .filter(f => f.importance > 0.1)
            .length;

        if (importantFeatures < 5) {
            recommendations.push({
                type: 'feature_engineering',
                message: 'Consider adding more relevant features or increasing window size',
                priority: 'medium'
            });
        }

        // Analyze optimization history
        if (performance.optimization?.improvement < 10) {
            recommendations.push({
                type: 'hyperparameter_tuning',
                message: 'Consider expanding hyperparameter search space',
                priority: 'low'
            });
        }

        return recommendations;
    }
}

module.exports = ModelOptimizer;
