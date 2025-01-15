const { describe, expect, beforeEach, afterEach } = require('@jest/globals');
const fs = require('fs');
const path = require('path');
const os = require('os');
const DataExporter = require('./data-exporter');
const {
    validResults,
    invalidResults,
    generateLargeResults,
    expectedOutputs,
    specialPaths
} = require('./fixtures/export.fixtures');

describe('DataExporter', () => {
    let exporter;
    let tempDir;

    beforeEach(() => {
        tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'data-exporter-test-'));
        exporter = new DataExporter(tempDir);
    });

    afterEach(() => {
        fs.rmSync(tempDir, { recursive: true, force: true });
    });

    describe('JSON Export', () => {
        test('exports basic results correctly', async () => {
            await exporter.exportToJson(validResults.basic);
            const jsonPath = path.join(tempDir, 'results.json');

            expect(fs.existsSync(jsonPath)).toBe(true);

            const exported = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
            expect(exported.results).toEqual(validResults.basic);
            expectedOutputs.json.requiredFields.forEach(field => {
                expect(exported.metadata).toHaveProperty(field);
            });
            expect(exported.metadata.version).toBe(expectedOutputs.json.metadata.version);
        });

        test('handles special characters correctly', async () => {
            await exporter.exportToJson(validResults.withSpecialChars);
            const jsonPath = path.join(tempDir, 'results.json');

            const exported = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
            expect(exported.results.executionTimes).toEqual(validResults.withSpecialChars.executionTimes);
        });

        test('handles Unicode characters correctly', async () => {
            await exporter.exportToJson(validResults.withUnicode);
            const jsonPath = path.join(tempDir, 'results.json');

            const exported = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
            expect(exported.results.executionTimes).toEqual(validResults.withUnicode.executionTimes);
        });
    });

    describe('CSV Export', () => {
        test('exports basic results correctly', async () => {
            await exporter.exportToCsv(validResults.basic);
            const csvPath = path.join(tempDir, 'results.csv');

            expect(fs.existsSync(csvPath)).toBe(true);

            const content = fs.readFileSync(csvPath, 'utf8');
            const lines = content.trim().split('\n');

            // Verify headers
            expect(lines[0].split(',')).toEqual(expectedOutputs.csv.headers);

            // Verify data
            Object.entries(validResults.basic.executionTimes).forEach(([name, time], index) => {
                const line = lines[index + 1];
                expect(line).toContain(name);
                expect(line).toContain(time.toFixed(2));
            });
        });

        test('handles missing data gracefully', async () => {
            await exporter.exportToCsv(invalidResults.missingData);
            const csvPath = path.join(tempDir, 'results.csv');

            const content = fs.readFileSync(csvPath, 'utf8');
            const lines = content.trim().split('\n');
            expect(lines[0].split(',')).toHaveLength(expectedOutputs.csv.requiredColumns);
        });

        test('escapes special characters correctly', async () => {
            await exporter.exportToCsv(validResults.withSpecialChars);
            const csvPath = path.join(tempDir, 'results.csv');

            const content = fs.readFileSync(csvPath, 'utf8');
            Object.keys(validResults.withSpecialChars.executionTimes).forEach(name => {
                expect(content).toContain(name.replace(/[,"\r\n]/g, '_'));
            });
        });
    });

    describe('Excel Export', () => {
        test('creates all required sheets', async () => {
            await exporter.exportToExcel(validResults.basic);
            const excelPath = path.join(tempDir, 'results.xlsx');

            const workbook = new ExcelJS.Workbook();
            await workbook.xlsx.readFile(excelPath);

            expectedOutputs.excel.sheets.forEach(sheetName => {
                expect(workbook.getWorksheet(sheetName)).toBeTruthy();
            });
        });

        test('includes required sections in summary', async () => {
            await exporter.exportToExcel(validResults.basic);
            const excelPath = path.join(tempDir, 'results.xlsx');

            const workbook = new ExcelJS.Workbook();
            await workbook.xlsx.readFile(excelPath);
            const summary = workbook.getWorksheet('Summary');

            expectedOutputs.excel.validations.summary.requiredSections.forEach(section => {
                const cell = summary.findCell(c => c.value === section);
                expect(cell).toBeTruthy();
            });
        });

        test('applies conditional formatting correctly', async () => {
            await exporter.exportToExcel(validResults.basic);
            const excelPath = path.join(tempDir, 'results.xlsx');

            const workbook = new ExcelJS.Workbook();
            await workbook.xlsx.readFile(excelPath);
            const details = workbook.getWorksheet('Details');

            expectedOutputs.excel.validations.details.formatting.conditionalColumns.forEach(colIndex => {
                const column = details.getColumn(colIndex);
                expect(column.dataValidation).toBeTruthy();
            });
        });
    });

    describe('Markdown Export', () => {
        test('includes all required sections', async () => {
            await exporter.exportToMarkdown(validResults.basic);
            const mdPath = path.join(tempDir, 'results.md');

            const content = fs.readFileSync(mdPath, 'utf8');
            expectedOutputs.markdown.sections.forEach(section => {
                expect(content).toMatch(new RegExp(`## ${section}`));
            });
        });

        test('creates valid table structure', async () => {
            await exporter.exportToMarkdown(validResults.basic);
            const mdPath = path.join(tempDir, 'results.md');

            const content = fs.readFileSync(mdPath, 'utf8');
            const tableLines = content.split('\n').filter(line => line.startsWith('|'));

            // Check headers
            expect(tableLines[0].split('|').slice(1, -1).map(h => h.trim()))
                .toEqual(expectedOutputs.markdown.tableHeaders);

            // Check separator
            expect(tableLines[1]).toMatch(/^\|[-|]*\|$/);

            // Check data rows
            Object.keys(validResults.basic.executionTimes).forEach(name => {
                expect(content).toContain(`| ${name} |`);
            });
        });

        test('formats numbers consistently', async () => {
            await exporter.exportToMarkdown(validResults.basic);
            const mdPath = path.join(tempDir, 'results.md');

            const content = fs.readFileSync(mdPath, 'utf8');
            const numberPattern = /\d+\.\d{2}/g;
            const numbers = content.match(numberPattern);
            expect(numbers).toBeTruthy();
            numbers.forEach(num => {
                expect(num).toMatch(/^\d+\.\d{2}$/);
            });
        });
    });

    describe('Multiple Format Export', () => {
        test('exports all formats successfully', async () => {
            await exporter.exportResults(validResults.basic);

            const files = ['json', 'csv', 'xlsx', 'md'].map(ext =>
                path.join(tempDir, `results.${ext}`)
            );

            files.forEach(file => {
                expect(fs.existsSync(file)).toBe(true);
                expect(fs.statSync(file).size).toBeGreaterThan(0);
            });
        });

        test('handles large datasets', async () => {
            const largeResults = generateLargeResults(1000);
            await exporter.exportResults(largeResults);

            const files = ['json', 'csv', 'xlsx', 'md'].map(ext =>
                path.join(tempDir, `results.${ext}`)
            );

            files.forEach(file => {
                expect(fs.existsSync(file)).toBe(true);
                const stats = fs.statSync(file);
                expect(stats.size).toBeGreaterThan(0);
            });
        });
    });

    describe('Path Handling', () => {
        test.each(Object.entries(specialPaths))(
            'handles %s paths correctly',
            async (_, dirPath) => {
                const specialDir = path.join(tempDir, dirPath);
                const specialExporter = new DataExporter(specialDir);

                await specialExporter.exportResults(validResults.basic);

                const files = ['json', 'csv', 'xlsx', 'md'].map(ext =>
                    path.join(specialDir, `results.${ext}`)
                );

                files.forEach(file => {
                    expect(fs.existsSync(file)).toBe(true);
                });
            }
        );
    });

    describe('Error Handling', () => {
        test.each(Object.entries(invalidResults))(
            'handles %s gracefully',
            async (_, results) => {
                await expect(exporter.exportResults(results))
                    .rejects
                    .toThrow();
            }
        );

        test('handles file system errors', async () => {
            const readOnlyDir = path.join(tempDir, 'readonly');
            fs.mkdirSync(readOnlyDir, { mode: 0o444 });

            const readOnlyExporter = new DataExporter(readOnlyDir);
            await expect(readOnlyExporter.exportResults(validResults.basic))
                .rejects
                .toThrow();
        });
    });
});
