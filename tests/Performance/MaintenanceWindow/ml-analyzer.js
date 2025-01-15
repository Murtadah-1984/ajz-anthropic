const tf = require('@tensorflow/tfjs-node');
const MetricsAnalyzer = require('./metrics-analyzer');

/**
 * Machine learning performance analyzer
 */
class MLAnalyzer extends MetricsAnalyzer {
    constructor(metricsStore) {
        super(metricsStore);
        this.models = new Map();
        this.windowSize = 24; // 24 hours for daily patterns
    }

    /**
     * Prepare data for ML models
     */
    prepareData(values, windowSize = this.windowSize) {
        const X = [];
        const y = [];

        for (let i = 0; i < values.length - windowSize; i++) {
            X.push(values.slice(i, i + windowSize));
            y.push(values[i + windowSize]);
        }

        return {
            X: tf.tensor2d(X),
            y: tf.tensor1d(y)
        };
    }

    /**
     * Create LSTM model for time series prediction
     */
    createLSTMModel(inputShape) {
        const model = tf.sequential();

        model.add(tf.layers.lstm({
            units: 50,
            inputShape: [inputShape, 1],
            returnSequences: true
        }));

        model.add(tf.layers.dropout({ rate: 0.2 }));

        model.add(tf.layers.lstm({
            units: 50,
            returnSequences: false
        }));

        model.add(tf.layers.dense({ units: 1 }));

        model.compile({
            optimizer: tf.train.adam(0.001),
            loss: 'meanSquaredError'
        });

        return model;
    }

    /**
     * Train prediction model
     */
    async trainModel(type, name, timeRange = '30d') {
        const data = await this.store.getTimeSeries(type, name, '1h', timeRange);
        const values = data.map(d => d.average);

        // Normalize data
        const min = Math.min(...values);
        const max = Math.max(...values);
        const normalizedValues = values.map(v => (v - min) / (max - min));

        // Prepare training data
        const { X, y } = this.prepareData(normalizedValues);

        // Create and train model
        const model = this.createLSTMModel(this.windowSize);

        await model.fit(
            X.reshape([X.shape[0], X.shape[1], 1]),
            y,
            {
                epochs: 100,
                batchSize: 32,
                validationSplit: 0.2,
                callbacks: {
                    onEpochEnd: (epoch, logs) => {
                        console.log(`Epoch ${epoch + 1}: loss = ${logs.loss.toFixed(4)}`);
                    }
                }
            }
        );

        // Save model for future predictions
        this.models.set(`${type}:${name}`, {
            model,
            min,
            max
        });

        return model;
    }

    /**
     * Generate ML-based forecast
     */
    async generateMLForecast(type, name, periods = 24) {
        let modelInfo = this.models.get(`${type}:${name}`);

        if (!modelInfo) {
            await this.trainModel(type, name);
            modelInfo = this.models.get(`${type}:${name}`);
        }

        const { model, min, max } = modelInfo;

        // Get recent data
        const data = await this.store.getTimeSeries(type, name, '1h', '24h');
        const values = data.map(d => d.average);
        const normalizedValues = values.map(v => (v - min) / (max - min));

        // Generate predictions
        const predictions = [];
        let currentInput = normalizedValues.slice(-this.windowSize);

        for (let i = 0; i < periods; i++) {
            const input = tf.tensor2d([currentInput]).reshape([1, this.windowSize, 1]);
            const prediction = model.predict(input);
            const value = prediction.dataSync()[0];
            predictions.push(value);

            currentInput = [...currentInput.slice(1), value];
        }

        // Denormalize predictions
        const denormalizedPredictions = predictions.map(v => v * (max - min) + min);

        return {
            values: denormalizedPredictions,
            confidence: this.calculateMLConfidenceIntervals(denormalizedPredictions, values)
        };
    }

    /**
     * Calculate ML confidence intervals
     */
    calculateMLConfidenceIntervals(predictions, historical) {
        const errors = [];
        const modelInfo = this.models.get(`${type}:${name}`);
        const validationLoss = modelInfo.model.evaluateInternal().dataSync()[0];

        // Calculate prediction intervals based on model uncertainty
        const stdDev = Math.sqrt(validationLoss);
        const confidence = 1.96 * stdDev; // 95% confidence interval

        return predictions.map(value => ({
            lower: value - confidence,
            upper: value + confidence
        }));
    }

    /**
     * Detect anomalies using autoencoders
     */
    async detectMLAnomalies(type, name, timeRange = '7d') {
        const data = await this.store.getTimeSeries(type, name, '1h', timeRange);
        const values = data.map(d => d.average);

        // Create autoencoder model
        const encoder = this.createAutoencoder(this.windowSize);
        const { X } = this.prepareData(values);

        // Train autoencoder
        await encoder.fit(
            X.reshape([X.shape[0], X.shape[1], 1]),
            X.reshape([X.shape[0], X.shape[1], 1]),
            {
                epochs: 50,
                batchSize: 32
            }
        );

        // Detect anomalies
        const reconstructed = encoder.predict(X.reshape([X.shape[0], X.shape[1], 1]));
        const losses = tf.sub(
            X.reshape([X.shape[0], X.shape[1], 1]),
            reconstructed
        ).square().mean(1).dataSync();

        // Calculate anomaly threshold
        const meanLoss = tf.mean(losses).dataSync()[0];
        const stdLoss = tf.std(losses).dataSync()[0];
        const threshold = meanLoss + 2 * stdLoss;

        // Identify anomalies
        const anomalies = losses.map((loss, index) => {
            return loss > threshold ? {
                index: index + this.windowSize,
                value: values[index + this.windowSize],
                score: loss,
                deviation: loss - meanLoss
            } : null;
        }).filter(Boolean);

        return {
            anomalies,
            count: anomalies.length,
            percentage: (anomalies.length / values.length) * 100,
            threshold
        };
    }

    /**
     * Create autoencoder model
     */
    createAutoencoder(inputShape) {
        const model = tf.sequential();

        // Encoder
        model.add(tf.layers.lstm({
            units: 32,
            inputShape: [inputShape, 1],
            returnSequences: true
        }));

        model.add(tf.layers.lstm({
            units: 16,
            returnSequences: true
        }));

        // Decoder
        model.add(tf.layers.lstm({
            units: 16,
            returnSequences: true
        }));

        model.add(tf.layers.lstm({
            units: 32,
            returnSequences: true
        }));

        model.add(tf.layers.timeDistributed({
            layer: tf.layers.dense({ units: 1 })
        }));

        model.compile({
            optimizer: tf.train.adam(0.001),
            loss: 'meanSquaredError'
        });

        return model;
    }

    /**
     * Analyze performance using ML
     */
    async analyzeWithML(type, name, timeRange = '7d') {
        const [forecast, anomalies] = await Promise.all([
            this.generateMLForecast(type, name),
            this.detectMLAnomalies(type, name, timeRange)
        ]);

        return {
            forecast,
            anomalies,
            model: {
                type: 'LSTM',
                windowSize: this.windowSize,
                parameters: this.models.get(`${type}:${name}`)?.model.countParams() || 0
            }
        };
    }

    /**
     * Generate ML-enhanced report
     */
    async generateMLReport(timeRange = '7d') {
        const baseReport = await this.generateReport(timeRange);

        for (const metric of baseReport.metrics) {
            const mlAnalysis = await this.analyzeWithML(
                metric.type,
                metric.name,
                timeRange
            );

            metric.mlAnalysis = mlAnalysis;
        }

        return baseReport;
    }
}

module.exports = MLAnalyzer;
