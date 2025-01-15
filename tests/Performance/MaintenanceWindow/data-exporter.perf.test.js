const { describe, expect, beforeEach, afterEach } = require('@jest/globals');
const fs = require('fs');
const path = require('path');
const os = require('os');
const DataExporter = require('./data-exporter');
const {
    validResults,
    generateLargeResults
} = require('./fixtures/export.fixtures');

describe('DataExporter Performance', () => {
    let exporter;
    let tempDir;
    let startTime;
    let endTime;
    const memorySnapshots = [];

    beforeEach(() => {
        tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'data-exporter-perf-test-'));
        exporter = new DataExporter(tempDir);
        startTime = process.hrtime();
        memorySnapshots.length = 0;
    });

    afterEach(() => {
        fs.rmSync(tempDir, { recursive: true, force: true });
    });

    /**
     * Take memory snapshot
     */
    const takeMemorySnapshot = (label) => {
        const memory = process.memoryUsage();
        memorySnapshots.push({
            label,
            heapUsed: memory.heapUsed / 1024 / 1024, // MB
            heapTotal: memory.heapTotal / 1024 / 1024,
            external: memory.external / 1024 / 1024,
            rss: memory.rss / 1024 / 1024
        });
    };

    /**
     * Calculate execution time
     */
    const getExecutionTime = () => {
        const diff = process.hrtime(startTime);
        return (diff[0] * 1e9 + diff[1]) / 1e6; // Convert to milliseconds
    };

    /**
     * Performance test wrapper
     */
    const measurePerformance = async (label, fn, thresholds) => {
        takeMemorySnapshot(`${label} - Start`);
        startTime = process.hrtime();

        await fn();

        const executionTime = getExecutionTime();
        takeMemorySnapshot(`${label} - End`);

        // Calculate memory impact
        const startSnapshot = memorySnapshots[memorySnapshots.length - 2];
        const endSnapshot = memorySnapshots[memorySnapshots.length - 1];
        const memoryImpact = endSnapshot.heapUsed - startSnapshot.heapUsed;

        // Log performance metrics
        console.log(`\nPerformance Test: ${label}`);
        console.log(`Execution Time: ${executionTime.toFixed(2)}ms`);
        console.log(`Memory Impact: ${memoryImpact.toFixed(2)}MB`);
        console.log('Memory Details:');
        console.log(`  Heap Used: ${endSnapshot.heapUsed.toFixed(2)}MB`);
        console.log(`  Heap Total: ${endSnapshot.heapTotal.toFixed(2)}MB`);
        console.log(`  External: ${endSnapshot.external.toFixed(2)}MB`);
        console.log(`  RSS: ${endSnapshot.rss.toFixed(2)}MB`);

        // Verify thresholds
        if (thresholds) {
            if (thresholds.executionTime) {
                expect(executionTime).toBeLessThan(thresholds.executionTime);
            }
            if (thresholds.memoryImpact) {
                expect(Math.abs(memoryImpact)).toBeLessThan(thresholds.memoryImpact);
            }
        }

        return { executionTime, memoryImpact };
    };

    describe('Scaling Tests', () => {
        const dataSizes = [100, 1000, 10000];

        test.each(dataSizes)(
            'handles %d records efficiently',
            async (size) => {
                const results = generateLargeResults(size);
                const expectedTime = size * 0.1; // 0.1ms per record
                const expectedMemory = size * 0.001; // 0.001MB per record

                await measurePerformance(
                    `Export ${size} Records`,
                    async () => await exporter.exportResults(results),
                    {
                        executionTime: expectedTime,
                        memoryImpact: expectedMemory
                    }
                );
            }
        );

        test('demonstrates linear scaling', async () => {
            const timings = [];

            for (const size of dataSizes) {
                const results = generateLargeResults(size);
                const { executionTime } = await measurePerformance(
                    `Export ${size} Records`,
                    async () => await exporter.exportResults(results)
                );
                timings.push({ size, time: executionTime });
            }

            // Verify linear scaling (time should increase roughly linearly with size)
            for (let i = 1; i < timings.length; i++) {
                const scaleFactor = timings[i].time / timings[i - 1].time;
                const sizeFactor = timings[i].size / timings[i - 1].size;
                const ratio = scaleFactor / sizeFactor;

                // Allow for some variance but should be roughly linear
                expect(ratio).toBeGreaterThan(0.5);
                expect(ratio).toBeLessThan(2);
            }
        });
    });

    describe('Format-Specific Performance', () => {
        const results = generateLargeResults(1000);

        test('JSON export performance', async () => {
            await measurePerformance(
                'JSON Export',
                async () => await exporter.exportToJson(results),
                {
                    executionTime: 100,
                    memoryImpact: 10
                }
            );
        });

        test('CSV export performance', async () => {
            await measurePerformance(
                'CSV Export',
                async () => await exporter.exportToCsv(results),
                {
                    executionTime: 150,
                    memoryImpact: 15
                }
            );
        });

        test('Excel export performance', async () => {
            await measurePerformance(
                'Excel Export',
                async () => await exporter.exportToExcel(results),
                {
                    executionTime: 500,
                    memoryImpact: 50
                }
            );
        });

        test('Markdown export performance', async () => {
            await measurePerformance(
                'Markdown Export',
                async () => await exporter.exportToMarkdown(results),
                {
                    executionTime: 200,
                    memoryImpact: 20
                }
            );
        });
    });

    describe('Memory Management', () => {
        test('releases memory after large export', async () => {
            const initialMemory = process.memoryUsage().heapUsed;
            const results = generateLargeResults(10000);

            await exporter.exportResults(results);

            // Force garbage collection if available
            if (global.gc) {
                global.gc();
            }

            const finalMemory = process.memoryUsage().heapUsed;
            const memoryDiff = (finalMemory - initialMemory) / 1024 / 1024; // MB

            // Should not retain more than 10MB after cleanup
            expect(memoryDiff).toBeLessThan(10);
        });

        test('handles concurrent exports efficiently', async () => {
            const results = generateLargeResults(1000);
            const concurrentExports = 5;

            const initialMemory = process.memoryUsage().heapUsed;

            await measurePerformance(
                'Concurrent Exports',
                async () => {
                    await Promise.all(
                        Array(concurrentExports).fill(0).map((_, i) =>
                            exporter.exportResults(results, `export_${i}`)
                        )
                    );
                },
                {
                    // Allow 100ms per export
                    executionTime: 100 * concurrentExports,
                    // Allow 10MB per concurrent export
                    memoryImpact: 10 * concurrentExports
                }
            );

            if (global.gc) {
                global.gc();
            }

            const finalMemory = process.memoryUsage().heapUsed;
            const memoryDiff = (finalMemory - initialMemory) / 1024 / 1024;

            // Should not retain more than 2MB per concurrent export after cleanup
            expect(memoryDiff).toBeLessThan(2 * concurrentExports);
        });
    });

    describe('Resource Usage', () => {
        test('CPU usage remains reasonable', async () => {
            const results = generateLargeResults(5000);
            let cpuUsage = process.cpuUsage();

            await exporter.exportResults(results);

            cpuUsage = process.cpuUsage(cpuUsage);
            const cpuPercent = (cpuUsage.user + cpuUsage.system) / 1000000; // Convert to seconds

            // CPU time should be less than 50% of wall time
            expect(cpuPercent).toBeLessThan(getExecutionTime() * 0.5);
        });

        test('file I/O is efficient', async () => {
            const results = generateLargeResults(1000);
            const fileSizes = {};

            await exporter.exportResults(results);

            ['json', 'csv', 'xlsx', 'md'].forEach(ext => {
                const file = path.join(tempDir, `results.${ext}`);
                const stats = fs.statSync(file);
                fileSizes[ext] = stats.size / 1024; // KB
            });

            // Verify reasonable file sizes
            expect(fileSizes.json).toBeLessThan(1024); // 1MB
            expect(fileSizes.csv).toBeLessThan(512); // 512KB
            expect(fileSizes.xlsx).toBeLessThan(2048); // 2MB
            expect(fileSizes.md).toBeLessThan(256); // 256KB
        });
    });

    describe('Stress Testing', () => {
        test('handles repeated exports', async () => {
            const results = generateLargeResults(1000);
            const iterations = 10;
            const timings = [];

            for (let i = 0; i < iterations; i++) {
                const { executionTime } = await measurePerformance(
                    `Iteration ${i + 1}`,
                    async () => await exporter.exportResults(results)
                );
                timings.push(executionTime);
            }

            // Calculate timing consistency
            const avgTime = timings.reduce((a, b) => a + b) / timings.length;
            const variance = timings.reduce((sq, n) => sq + Math.pow(n - avgTime, 2), 0) / timings.length;
            const stdDev = Math.sqrt(variance);

            // Standard deviation should be less than 25% of mean
            expect(stdDev / avgTime).toBeLessThan(0.25);
        });

        test('handles system under load', async () => {
            const results = generateLargeResults(1000);
            const loadGenerators = [];

            // Generate some CPU load
            for (let i = 0; i < os.cpus().length; i++) {
                loadGenerators.push(
                    new Promise(resolve => {
                        let x = 0;
                        for (let j = 0; j < 1000000; j++) {
                            x += Math.random();
                        }
                        resolve(x);
                    })
                );
            }

            const { executionTime } = await measurePerformance(
                'Export Under Load',
                async () => {
                    await Promise.all([
                        exporter.exportResults(results),
                        ...loadGenerators
                    ]);
                },
                {
                    // Allow 2x normal execution time under load
                    executionTime: 1000,
                    memoryImpact: 100
                }
            );
        });
    });
});
