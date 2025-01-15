import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Custom metrics
const successRate = new Rate('success_rate');
const extensionDuration = new Trend('extension_duration');
const endingDuration = new Trend('ending_duration');
const cachingEfficiency = new Trend('caching_efficiency');

// Test configuration
export const options = {
    scenarios: {
        // Simulate normal usage
        normal_load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '2m', target: 50 }, // Ramp up
                { duration: '5m', target: 50 }, // Stay at peak
                { duration: '2m', target: 0 },  // Ramp down
            ],
            gracefulRampDown: '30s',
        },
        // Simulate high load during maintenance windows
        high_load: {
            executor: 'constant-arrival-rate',
            rate: 100,
            timeUnit: '1s',
            duration: '5m',
            preAllocatedVUs: 100,
            maxVUs: 200,
        },
        // Simulate spike in traffic
        spike: {
            executor: 'ramping-arrival-rate',
            startRate: 50,
            timeUnit: '1s',
            stages: [
                { duration: '30s', target: 500 }, // Quick ramp-up
                { duration: '1m', target: 500 },  // Stay at peak
                { duration: '30s', target: 50 },  // Quick ramp-down
            ],
            preAllocatedVUs: 500,
            maxVUs: 1000,
        },
    },
    thresholds: {
        'success_rate': ['rate>0.95'], // 95% success rate
        'extension_duration': ['p95<500'], // 95% of extensions under 500ms
        'ending_duration': ['p95<500'], // 95% of endings under 500ms
        'http_req_duration': ['p95<1000'], // 95% of requests under 1s
        'http_req_failed': ['rate<0.05'], // Less than 5% failure rate
    },
};

// Test setup
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const API_TOKEN = __ENV.API_TOKEN || 'test-token';

const headers = {
    'Authorization': `Bearer ${API_TOKEN}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

// Main test function
export default function () {
    group('Maintenance Window Operations', function () {
        // List active windows (should be cached)
        group('List Active Windows', function () {
            const startTime = Date.now();
            const response = http.get(`${BASE_URL}/api/maintenance-windows/active`, { headers });

            check(response, {
                'status is 200': (r) => r.status === 200,
                'has windows array': (r) => Array.isArray(r.json().windows),
            });

            // Measure cache efficiency
            cachingEfficiency.add(Date.now() - startTime);
        });

        // Create a new window
        let windowId;
        group('Create Window', function () {
            const payload = {
                environment: 'test',
                start_time: new Date(Date.now() + 3600000).toISOString(), // 1 hour from now
                duration: randomIntBetween(1, 4),
                comment: 'Load test maintenance window',
            };

            const response = http.post(
                `${BASE_URL}/api/maintenance-windows`,
                JSON.stringify(payload),
                { headers }
            );

            check(response, {
                'status is 201': (r) => r.status === 201,
                'returns window id': (r) => r.json().window.id !== undefined,
            });

            if (response.status === 201) {
                windowId = response.json().window.id;
                successRate.add(1);
            } else {
                successRate.add(0);
            }
        });

        // Extend window
        if (windowId) {
            group('Extend Window', function () {
                const startTime = Date.now();
                const payload = {
                    duration: 1,
                    reason: 'Load test extension',
                };

                const response = http.post(
                    `${BASE_URL}/api/maintenance-windows/${windowId}/extend`,
                    JSON.stringify(payload),
                    { headers }
                );

                check(response, {
                    'status is 200': (r) => r.status === 200,
                    'window is extended': (r) => r.json().window.duration > 1,
                });

                extensionDuration.add(Date.now() - startTime);
                successRate.add(response.status === 200);
            });

            // End window
            group('End Window', function () {
                const startTime = Date.now();
                const payload = {
                    reason: 'Load test completion',
                    completion_status: 'completed',
                };

                const response = http.post(
                    `${BASE_URL}/api/maintenance-windows/${windowId}/end`,
                    JSON.stringify(payload),
                    { headers }
                );

                check(response, {
                    'status is 200': (r) => r.status === 200,
                    'window is ended': (r) => r.json().window.status === 'completed',
                });

                endingDuration.add(Date.now() - startTime);
                successRate.add(response.status === 200);
            });
        }

        // Random sleep between operations
        sleep(randomIntBetween(1, 5));
    });
}

// Setup and teardown
export function setup() {
    // Create test data if needed
    const response = http.post(
        `${BASE_URL}/api/maintenance-windows/setup-test`,
        null,
        { headers }
    );
    return { setupData: response.json() };
}

export function teardown(data) {
    // Cleanup test data
    http.post(
        `${BASE_URL}/api/maintenance-windows/cleanup-test`,
        JSON.stringify(data.setupData),
        { headers }
    );
}

// Handle test lifecycle
export function handleSummary(data) {
    return {
        'stdout': textSummary(data, { indent: ' ', enableColors: true }),
        './load-test-results.json': JSON.stringify(data),
        './load-test-metrics.html': generateHtmlReport(data),
    };
}

// Helper function to generate HTML report
function generateHtmlReport(data) {
    return `
        <!DOCTYPE html>
        <html>
            <head>
                <title>Load Test Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .metric { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
                    .success { color: green; }
                    .failure { color: red; }
                </style>
            </head>
            <body>
                <h1>Load Test Results</h1>
                <div class="metric">
                    <h2>Success Rate</h2>
                    <p class="${data.metrics.success_rate.values.rate > 0.95 ? 'success' : 'failure'}">
                        ${(data.metrics.success_rate.values.rate * 100).toFixed(2)}%
                    </p>
                </div>
                <div class="metric">
                    <h2>Response Times (p95)</h2>
                    <p>Extension: ${data.metrics.extension_duration.values.p95.toFixed(2)}ms</p>
                    <p>Ending: ${data.metrics.ending_duration.values.p95.toFixed(2)}ms</p>
                </div>
                <div class="metric">
                    <h2>Cache Efficiency</h2>
                    <p>Average: ${data.metrics.caching_efficiency.values.avg.toFixed(2)}ms</p>
                </div>
            </body>
        </html>
    `;
}
