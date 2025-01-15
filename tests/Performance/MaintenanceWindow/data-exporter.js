const fs = require('fs');
const path = require('path');
const ExcelJS = require('exceljs');

/**
 * Data export utilities for benchmark results
 */
class DataExporter {
    constructor(outputDir = 'benchmark-results') {
        this.outputDir = outputDir;
    }

    /**
     * Export results to multiple formats
     */
    async exportResults(results) {
        await Promise.all([
            this.exportToJson(results),
            this.exportToCsv(results),
            this.exportToExcel(results),
            this.exportToMarkdown(results)
        ]);
    }

    /**
     * Export to JSON format
     */
    exportToJson(results) {
        const jsonPath = path.join(this.outputDir, 'results.json');
        const jsonData = {
            metadata: {
                timestamp: new Date().toISOString(),
                version: '1.0.0',
                environment: process.env.NODE_ENV || 'development'
            },
            results
        };

        fs.writeFileSync(jsonPath, JSON.stringify(jsonData, null, 2));
        console.log(`JSON results exported to ${jsonPath}`);
    }

    /**
     * Export to CSV format
     */
    exportToCsv(results) {
        const csvPath = path.join(this.outputDir, 'results.csv');
        const headers = ['Test Case', 'Execution Time (ms)', 'Memory Usage (MB)', 'Samples', 'Std Dev'];

        const rows = Object.keys(results.executionTimes).map(name => [
            name,
            results.executionTimes[name].toFixed(2),
            results.memoryUsage[name].toFixed(2),
            results.details[name].samples,
            results.details[name].deviation.toFixed(2)
        ]);

        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');

        fs.writeFileSync(csvPath, csvContent);
        console.log(`CSV results exported to ${csvPath}`);
    }

    /**
     * Export to Excel format
     */
    async exportToExcel(results) {
        const excelPath = path.join(this.outputDir, 'results.xlsx');
        const workbook = new ExcelJS.Workbook();

        // Summary sheet
        const summarySheet = workbook.addWorksheet('Summary');
        this.createSummarySheet(summarySheet, results);

        // Details sheet
        const detailsSheet = workbook.addWorksheet('Details');
        this.createDetailsSheet(detailsSheet, results);

        // Charts sheet
        const chartsSheet = workbook.addWorksheet('Charts');
        this.createChartsSheet(chartsSheet, results);

        await workbook.xlsx.writeFile(excelPath);
        console.log(`Excel results exported to ${excelPath}`);
    }

    /**
     * Create summary sheet
     */
    createSummarySheet(sheet, results) {
        // Add title
        sheet.mergeCells('A1:E1');
        sheet.getCell('A1').value = 'Benchmark Results Summary';
        sheet.getCell('A1').font = { size: 14, bold: true };
        sheet.getCell('A1').alignment = { horizontal: 'center' };

        // Add metadata
        sheet.getCell('A3').value = 'Generated:';
        sheet.getCell('B3').value = new Date().toISOString();
        sheet.getCell('A4').value = 'Environment:';
        sheet.getCell('B4').value = process.env.NODE_ENV || 'development';

        // Add summary statistics
        const times = Object.values(results.executionTimes);
        const memory = Object.values(results.memoryUsage);

        sheet.getCell('A6').value = 'Execution Time Statistics (ms)';
        sheet.getCell('A6').font = { bold: true };
        sheet.getCell('A7').value = 'Minimum:';
        sheet.getCell('B7').value = Math.min(...times);
        sheet.getCell('A8').value = 'Maximum:';
        sheet.getCell('B8').value = Math.max(...times);
        sheet.getCell('A9').value = 'Average:';
        sheet.getCell('B9').value = times.reduce((a, b) => a + b) / times.length;

        sheet.getCell('A11').value = 'Memory Usage Statistics (MB)';
        sheet.getCell('A11').font = { bold: true };
        sheet.getCell('A12').value = 'Minimum:';
        sheet.getCell('B12').value = Math.min(...memory);
        sheet.getCell('A13').value = 'Maximum:';
        sheet.getCell('B13').value = Math.max(...memory);
        sheet.getCell('A14').value = 'Average:';
        sheet.getCell('B14').value = memory.reduce((a, b) => a + b) / memory.length;

        // Auto-fit columns
        sheet.columns.forEach(column => {
            column.width = 15;
        });
    }

    /**
     * Create details sheet
     */
    createDetailsSheet(sheet, results) {
        // Add headers
        const headers = ['Test Case', 'Execution Time (ms)', 'Memory Usage (MB)', 'Samples', 'Std Dev'];
        headers.forEach((header, i) => {
            sheet.getCell(1, i + 1).value = header;
            sheet.getCell(1, i + 1).font = { bold: true };
        });

        // Add data
        Object.keys(results.executionTimes).forEach((name, row) => {
            sheet.getCell(row + 2, 1).value = name;
            sheet.getCell(row + 2, 2).value = results.executionTimes[name];
            sheet.getCell(row + 2, 3).value = results.memoryUsage[name];
            sheet.getCell(row + 2, 4).value = results.details[name].samples;
            sheet.getCell(row + 2, 5).value = results.details[name].deviation;
        });

        // Add conditional formatting
        const executionTimeCol = sheet.getColumn(2);
        const memoryUsageCol = sheet.getColumn(3);

        this.addConditionalFormatting(executionTimeCol);
        this.addConditionalFormatting(memoryUsageCol);

        // Auto-fit columns
        sheet.columns.forEach(column => {
            column.width = Math.max(
                15,
                ...column.values.map(v => v ? v.toString().length : 0)
            );
        });
    }

    /**
     * Create charts sheet
     */
    createChartsSheet(sheet, results) {
        // Add execution time chart
        const timeChart = sheet.addChart('column', {
            title: { text: 'Execution Times' },
            legend: { position: 'right' },
            plotArea: {
                x: 0,
                y: 0,
                width: 480,
                height: 300
            },
            x: {
                title: { text: 'Test Case' }
            },
            y: {
                title: { text: 'Time (ms)' }
            },
            series: [{
                name: 'Execution Time',
                categories: Object.keys(results.executionTimes),
                values: Object.values(results.executionTimes)
            }]
        });
        sheet.addRow([]);
        sheet.addRow([]);
        sheet.addChart(timeChart);

        // Add memory usage chart
        const memoryChart = sheet.addChart('line', {
            title: { text: 'Memory Usage' },
            legend: { position: 'right' },
            plotArea: {
                x: 0,
                y: 0,
                width: 480,
                height: 300
            },
            x: {
                title: { text: 'Test Case' }
            },
            y: {
                title: { text: 'Memory (MB)' }
            },
            series: [{
                name: 'Memory Usage',
                categories: Object.keys(results.memoryUsage),
                values: Object.values(results.memoryUsage)
            }]
        });
        sheet.addRow([]);
        sheet.addRow([]);
        sheet.addChart(memoryChart);
    }

    /**
     * Add conditional formatting to column
     */
    addConditionalFormatting(column) {
        const values = column.values.slice(1).filter(v => typeof v === 'number');
        const avg = values.reduce((a, b) => a + b) / values.length;
        const stdDev = Math.sqrt(
            values.reduce((sq, n) => sq + Math.pow(n - avg, 2), 0) / (values.length - 1)
        );

        column.dataValidation = {
            type: 'custom',
            operator: 'custom',
            showErrorMessage: true,
            formulae: [`AND(${column.letter}2>${avg - 2 * stdDev}, ${column.letter}2<${avg + 2 * stdDev})`]
        };
    }

    /**
     * Export to Markdown format
     */
    exportToMarkdown(results) {
        const mdPath = path.join(this.outputDir, 'results.md');
        let markdown = `# Benchmark Results\n\n`;

        // Add metadata
        markdown += `## Metadata\n\n`;
        markdown += `- Generated: ${new Date().toISOString()}\n`;
        markdown += `- Environment: ${process.env.NODE_ENV || 'development'}\n\n`;

        // Add summary
        markdown += `## Summary\n\n`;
        const times = Object.values(results.executionTimes);
        const memory = Object.values(results.memoryUsage);

        markdown += `### Execution Time Statistics (ms)\n\n`;
        markdown += `- Minimum: ${Math.min(...times).toFixed(2)}\n`;
        markdown += `- Maximum: ${Math.max(...times).toFixed(2)}\n`;
        markdown += `- Average: ${(times.reduce((a, b) => a + b) / times.length).toFixed(2)}\n\n`;

        markdown += `### Memory Usage Statistics (MB)\n\n`;
        markdown += `- Minimum: ${Math.min(...memory).toFixed(2)}\n`;
        markdown += `- Maximum: ${Math.max(...memory).toFixed(2)}\n`;
        markdown += `- Average: ${(memory.reduce((a, b) => a + b) / memory.length).toFixed(2)}\n\n`;

        // Add detailed results
        markdown += `## Detailed Results\n\n`;
        markdown += `| Test Case | Execution Time (ms) | Memory Usage (MB) | Samples | Std Dev |\n`;
        markdown += `|-----------|-------------------|-----------------|---------|----------|\n`;

        Object.keys(results.executionTimes).forEach(name => {
            markdown += `| ${name} | ${results.executionTimes[name].toFixed(2)} | `;
            markdown += `${results.memoryUsage[name].toFixed(2)} | `;
            markdown += `${results.details[name].samples} | `;
            markdown += `${results.details[name].deviation.toFixed(2)} |\n`;
        });

        fs.writeFileSync(mdPath, markdown);
        console.log(`Markdown results exported to ${mdPath}`);
    }
}

// Run exporter if called directly
if (require.main === module) {
    const exporter = new DataExporter();
    const results = JSON.parse(fs.readFileSync(
        path.join(process.cwd(), 'benchmark-results', 'results.json')
    ));
    exporter.exportResults(results).catch(console.error);
}

module.exports = DataExporter;
