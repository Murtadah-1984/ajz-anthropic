import { describe, expect, it } from '@jest/globals';
import * as Guards from './guards';
import * as Types from './index';

describe('Common Type Guards', () => {
    describe('isTimeRange', () => {
        it('should return true for valid time ranges', () => {
            const validRanges = ['1h', '6h', '12h', '24h', '7d', '30d'];
            validRanges.forEach(range => {
                expect(Guards.isTimeRange(range)).toBe(true);
            });
        });

        it('should return false for invalid time ranges', () => {
            const invalidRanges = ['2h', '8h', '1d', '14d', 123, null, undefined, {}];
            invalidRanges.forEach(range => {
                expect(Guards.isTimeRange(range)).toBe(false);
            });
        });
    });

    describe('isMetricType', () => {
        it('should return true for valid metric types', () => {
            const validTypes = ['accuracy', 'performance', 'stability', 'reliability'];
            validTypes.forEach(type => {
                expect(Guards.isMetricType(type)).toBe(true);
            });
        });

        it('should return false for invalid metric types', () => {
            const invalidTypes = ['speed', 'memory', 123, null, undefined, {}];
            invalidTypes.forEach(type => {
                expect(Guards.isMetricType(type)).toBe(false);
            });
        });
    });
});

describe('Base Type Guards', () => {
    describe('isTrend', () => {
        it('should return true for valid trend objects', () => {
            const validTrend: Types.Trend = {
                slope: 1.5,
                intercept: 0.5,
                correlation: 0.95,
                significance: 0.01
            };
            expect(Guards.isTrend(validTrend)).toBe(true);
        });

        it('should return false for invalid trend objects', () => {
            const invalidTrends = [
                { slope: '1.5', intercept: 0.5, correlation: 0.95, significance: 0.01 },
                { slope: 1.5, correlation: 0.95, significance: 0.01 },
                { slope: 1.5, intercept: 0.5, correlation: '0.95', significance: 0.01 },
                null,
                undefined,
                123
            ];
            invalidTrends.forEach(trend => {
                expect(Guards.isTrend(trend)).toBe(false);
            });
        });
    });

    describe('isConfidenceInterval', () => {
        it('should return true for valid confidence intervals', () => {
            const validCI: Types.ConfidenceInterval = {
                lower: -1.5,
                upper: 1.5,
                confidence: 0.95
            };
            expect(Guards.isConfidenceInterval(validCI)).toBe(true);
        });

        it('should return false for invalid confidence intervals', () => {
            const invalidCIs = [
                { lower: '-1.5', upper: 1.5, confidence: 0.95 },
                { lower: -1.5, confidence: 0.95 },
                { lower: -1.5, upper: 1.5, confidence: '0.95' },
                null,
                undefined,
                123
            ];
            invalidCIs.forEach(ci => {
                expect(Guards.isConfidenceInterval(ci)).toBe(false);
            });
        });
    });
});

describe('Analysis Type Guards', () => {
    describe('isMemoryTrends', () => {
        it('should return true for valid memory trends', () => {
            const validTrends: Types.MemoryTrends = {
                trend: {
                    slope: 1.5,
                    intercept: 0.5,
                    correlation: 0.95,
                    significance: 0.01
                },
                growth: {
                    rate: 1.2,
                    pattern: 'linear',
                    stability: 0.8
                },
                leakProbability: 0.05,
                fragmentation: {
                    ratio: 0.3,
                    trend: {
                        slope: 0.1,
                        intercept: 0.2,
                        correlation: 0.8,
                        significance: 0.05
                    },
                    impact: 0.4
                }
            };
            expect(Guards.isMemoryTrends(validTrends)).toBe(true);
        });

        it('should return false for invalid memory trends', () => {
            const invalidTrends = [
                { trend: {}, growth: {}, leakProbability: '0.05', fragmentation: {} },
                { trend: {}, leakProbability: 0.05, fragmentation: {} },
                null,
                undefined,
                123
            ];
            invalidTrends.forEach(trends => {
                expect(Guards.isMemoryTrends(trends)).toBe(false);
            });
        });
    });
});

describe('Model Type Guards', () => {
    describe('isModelParameters', () => {
        it('should return true for valid model parameters', () => {
            const validParams: Types.ModelParameters = {
                lstm: {
                    units: 50,
                    layers: 2,
                    dropout: 0.2
                },
                optimizer: {
                    type: 'adam',
                    learningRate: 0.001,
                    beta1: 0.9,
                    beta2: 0.999
                },
                training: {
                    epochs: 100,
                    batchSize: 32,
                    validationSplit: 0.2
                }
            };
            expect(Guards.isModelParameters(validParams)).toBe(true);
        });

        it('should return false for invalid model parameters', () => {
            const invalidParams = [
                { lstm: {}, optimizer: {}, training: {} },
                { optimizer: {}, training: {} },
                null,
                undefined,
                123
            ];
            invalidParams.forEach(params => {
                expect(Guards.isModelParameters(params)).toBe(false);
            });
        });
    });
});

describe('Utility Functions', () => {
    describe('assertType', () => {
        it('should not throw for valid types', () => {
            const validTrend: Types.Trend = {
                slope: 1.5,
                intercept: 0.5,
                correlation: 0.95,
                significance: 0.01
            };
            expect(() => Guards.assertType(validTrend, Guards.isTrend, 'Invalid trend')).not.toThrow();
        });

        it('should throw for invalid types', () => {
            const invalidTrend = { slope: '1.5' };
            expect(() => Guards.assertType(invalidTrend, Guards.isTrend, 'Invalid trend')).toThrow(TypeError);
        });
    });

    describe('validateObject', () => {
        it('should return valid=true for valid objects', () => {
            const validTrend: Types.Trend = {
                slope: 1.5,
                intercept: 0.5,
                correlation: 0.95,
                significance: 0.01
            };
            const result = Guards.validateObject(validTrend, Guards.isTrend);
            expect(result.valid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        it('should return valid=false with errors for invalid objects', () => {
            const invalidTrend = { slope: '1.5' };
            const result = Guards.validateObject(invalidTrend, Guards.isTrend);
            expect(result.valid).toBe(false);
            expect(result.errors).toHaveLength(1);
        });
    });

    describe('validateArray', () => {
        it('should return valid=true for arrays of valid objects', () => {
            const validTrends: Types.Trend[] = [
                {
                    slope: 1.5,
                    intercept: 0.5,
                    correlation: 0.95,
                    significance: 0.01
                },
                {
                    slope: 2.0,
                    intercept: 1.0,
                    correlation: 0.90,
                    significance: 0.05
                }
            ];
            const result = Guards.validateArray(validTrends, Guards.isTrend);
            expect(result.valid).toBe(true);
            expect(result.errors).toHaveLength(0);
        });

        it('should return valid=false with errors for arrays with invalid objects', () => {
            const invalidTrends = [
                { slope: '1.5' },
                {
                    slope: 2.0,
                    intercept: 1.0,
                    correlation: 0.90,
                    significance: 0.05
                }
            ];
            const result = Guards.validateArray(invalidTrends, Guards.isTrend);
            expect(result.valid).toBe(false);
            expect(result.errors).toHaveLength(1);
        });
    });
});
