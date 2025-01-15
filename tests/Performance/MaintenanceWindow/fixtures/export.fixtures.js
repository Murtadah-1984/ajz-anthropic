/**
 * Test fixtures for data export testing
 */

const validResults = {
    basic: {
        executionTimes: {
            'Test A': 100.5,
            'Test B': 150.75,
            'Test C': 200.25
        },
        memoryUsage: {
            'Test A': 50.2,
            'Test B': 75.4,
            'Test C': 100.6
        },
        details: {
            'Test A': {
                samples: 100,
                deviation: 5.2,
                variance: 27.04,
                margin: 1.2
            },
            'Test B': {
                samples: 100,
                deviation: 7.5,
                variance: 56.25,
                margin: 1.5
            },
            'Test C': {
                samples: 100,
                deviation: 10.1,
                variance: 102.01,
                margin: 2.0
            }
        }
    },

    withSpecialChars: {
        executionTimes: {
            'Test/With/Slashes': 100,
            'Test With Spaces': 200,
            'Test.With.Dots': 300,
            'Test(With)Parentheses': 400,
            'Test[With]Brackets': 500,
            'Test{With}Braces': 600
        },
        memoryUsage: {
            'Test/With/Slashes': 50,
            'Test With Spaces': 60,
            'Test.With.Dots': 70,
            'Test(With)Parentheses': 80,
            'Test[With]Brackets': 90,
            'Test{With}Braces': 100
        },
        details: {
            'Test/With/Slashes': { samples: 100, deviation: 5, variance: 25, margin: 1 },
            'Test With Spaces': { samples: 100, deviation: 6, variance: 36, margin: 1.2 },
            'Test.With.Dots': { samples: 100, deviation: 7, variance: 49, margin: 1.4 },
            'Test(With)Parentheses': { samples: 100, deviation: 8, variance: 64, margin: 1.6 },
            'Test[With]Brackets': { samples: 100, deviation: 9, variance: 81, margin: 1.8 },
            'Test{With}Braces': { samples: 100, deviation: 10, variance: 100, margin: 2 }
        }
    },

    withUnicode: {
        executionTimes: {
            'Test with émojis 🎉': 100,
            'Test with àccents': 200,
            'Test with 漢字': 300,
            'Test with العربية': 400
        },
        memoryUsage: {
            'Test with émojis 🎉': 50,
            'Test with àccents': 60,
            'Test with 漢字': 70,
            'Test with العربية': 80
        },
        details: {
            'Test with émojis 🎉': { samples: 100, deviation: 5, variance: 25, margin: 1 },
            'Test with àccents': { samples: 100, deviation: 6, variance: 36, margin: 1.2 },
            'Test with 漢字': { samples: 100, deviation: 7, variance: 49, margin: 1.4 },
            'Test with العربية': { samples: 100, deviation: 8, variance: 64, margin: 1.6 }
        }
    }
};

const invalidResults = {
    missingData: {
        executionTimes: { 'Test A': 100 },
        memoryUsage: {},
        details: { 'Test A': { samples: 100, deviation: 5 } }
    },

    nullValues: {
        executionTimes: null,
        memoryUsage: undefined,
        details: {}
    },

    invalidTypes: {
        executionTimes: { 'Test A': '100' }, // String instead of number
        memoryUsage: { 'Test A': true }, // Boolean instead of number
        details: { 'Test A': 'invalid' } // String instead of object
    },

    emptyResults: {
        executionTimes: {},
        memoryUsage: {},
        details: {}
    }
};

const generateLargeResults = (size = 1000) => {
    const results = {
        executionTimes: {},
        memoryUsage: {},
        details: {}
    };

    for (let i = 0; i < size; i++) {
        const testName = `Test ${i}`;
        results.executionTimes[testName] = Math.random() * 1000;
        results.memoryUsage[testName] = Math.random() * 100;
        results.details[testName] = {
            samples: 100,
            deviation: Math.random() * 10,
            variance: Math.random() * 100,
            margin: Math.random() * 5
        };
    }

    return results;
};

const expectedOutputs = {
    json: {
        metadata: {
            version: '1.0.0'
        },
        requiredFields: ['timestamp', 'environment', 'results']
    },

    csv: {
        headers: ['Test Case', 'Execution Time (ms)', 'Memory Usage (MB)', 'Samples', 'Std Dev'],
        requiredColumns: 5
    },

    excel: {
        sheets: ['Summary', 'Details', 'Charts'],
        validations: {
            summary: {
                requiredSections: ['Execution Time Statistics', 'Memory Usage Statistics'],
                requiredMetrics: ['Minimum', 'Maximum', 'Average']
            },
            details: {
                columns: ['Test Case', 'Execution Time (ms)', 'Memory Usage (MB)', 'Samples', 'Std Dev'],
                formatting: {
                    conditionalColumns: [2, 3] // Columns with conditional formatting
                }
            }
        }
    },

    markdown: {
        sections: ['Metadata', 'Summary', 'Detailed Results'],
        tableHeaders: ['Test Case', 'Execution Time (ms)', 'Memory Usage (MB)', 'Samples', 'Std Dev']
    }
};

const specialPaths = {
    withSpaces: 'Directory With Spaces',
    withParentheses: 'Directory (With) Parentheses',
    withBrackets: 'Directory [With] Brackets',
    withSpecialChars: 'Directory_#@$%^&',
    withUnicode: 'Directory_with_émojis_🎉',
    deepNested: 'Level1/Level2/Level3/Output',
    withDots: '../Relative/Path/Output',
    withBackslash: 'Windows\\Style\\Path'
};

module.exports = {
    validResults,
    invalidResults,
    generateLargeResults,
    expectedOutputs,
    specialPaths
};
