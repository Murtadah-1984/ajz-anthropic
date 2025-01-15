#!/usr/bin/env node

const { program } = require('commander');
const inquirer = require('inquirer');
const chalk = require('chalk');
const ora = require('ora');
const path = require('path');
const fs = require('fs');
const PerformanceVisualizer = require('./visualize-performance');

program
    .version('1.0.0')
    .description('CLI tool for maintenance window performance analysis');

/**
 * Command to generate visualizations
 */
program
    .command('visualize')
    .description('Generate performance visualizations')
    .option('-d, --dir <directory>', 'Directory containing performance logs')
    .option('-o, --output <directory>', 'Output directory for visualizations')
    .option('-f, --format <format>', 'Output format (html, png, or both)', 'both')
    .action(async (options) => {
        try {
            const config = await promptForMissingOptions(options);
            await generateVisualizations(config);
        } catch (error) {
            console.error(chalk.red('Error:'), error.message);
            process.exit(1);
        }
    });

/**
 * Command to analyze specific test results
 */
program
    .command('analyze')
    .description('Analyze specific performance test results')
    .option('-f, --file <file>', 'Performance log file to analyze')
    .option('-t, --type <type>', 'Analysis type (basic, detailed, or comparison)')
    .action(async (options) => {
        try {
            const config = await promptForAnalysisOptions(options);
            await analyzeResults(config);
        } catch (error) {
            console.error(chalk.red('Error:'), error.message);
            process.exit(1);
        }
    });

/**
 * Command to compare multiple test results
 */
program
    .command('compare')
    .description('Compare multiple test results')
    .option('-f, --files <files...>', 'Performance log files to compare')
    .option('-m, --metrics <metrics...>', 'Metrics to compare')
    .action(async (options) => {
        try {
            const config = await promptForComparisonOptions(options);
            await compareResults(config);
        } catch (error) {
            console.error(chalk.red('Error:'), error.message);
            process.exit(1);
        }
    });

/**
 * Command to watch for new results and generate visualizations
 */
program
    .command('watch')
    .description('Watch for new test results and generate visualizations')
    .option('-d, --dir <directory>', 'Directory to watch')
    .option('-i, --interval <seconds>', 'Watch interval in seconds', '5')
    .action(async (options) => {
        try {
            const config = await promptForWatchOptions(options);
            await watchResults(config);
        } catch (error) {
            console.error(chalk.red('Error:'), error.message);
            process.exit(1);
        }
    });

/**
 * Prompt for missing visualization options
 */
async function promptForMissingOptions(options) {
    const questions = [];

    if (!options.dir) {
        questions.push({
            type: 'input',
            name: 'dir',
            message: 'Enter the directory containing performance logs:',
            default: path.join(process.cwd(), 'storage/logs'),
            validate: dir => fs.existsSync(dir) || 'Directory does not exist'
        });
    }

    if (!options.output) {
        questions.push({
            type: 'input',
            name: 'output',
            message: 'Enter the output directory for visualizations:',
            default: path.join(process.cwd(), 'storage/logs/charts')
        });
    }

    if (!options.format) {
        questions.push({
            type: 'list',
            name: 'format',
            message: 'Select output format:',
            choices: ['html', 'png', 'both'],
            default: 'both'
        });
    }

    const answers = await inquirer.prompt(questions);
    return { ...options, ...answers };
}

/**
 * Prompt for analysis options
 */
async function promptForAnalysisOptions(options) {
    const questions = [];

    if (!options.file) {
        const logDir = path.join(process.cwd(), 'storage/logs');
        const files = fs.readdirSync(logDir).filter(f => f.endsWith('.json'));

        questions.push({
            type: 'list',
            name: 'file',
            message: 'Select performance log file to analyze:',
            choices: files,
            validate: file => fs.existsSync(path.join(logDir, file)) || 'File does not exist'
        });
    }

    if (!options.type) {
        questions.push({
            type: 'list',
            name: 'type',
            message: 'Select analysis type:',
            choices: ['basic', 'detailed', 'comparison'],
            default: 'detailed'
        });
    }

    const answers = await inquirer.prompt(questions);
    return { ...options, ...answers };
}

/**
 * Prompt for comparison options
 */
async function promptForComparisonOptions(options) {
    const questions = [];

    if (!options.files || !options.files.length) {
        const logDir = path.join(process.cwd(), 'storage/logs');
        const files = fs.readdirSync(logDir).filter(f => f.endsWith('.json'));

        questions.push({
            type: 'checkbox',
            name: 'files',
            message: 'Select performance log files to compare:',
            choices: files,
            validate: files => files.length >= 2 || 'Please select at least 2 files'
        });
    }

    if (!options.metrics || !options.metrics.length) {
        questions.push({
            type: 'checkbox',
            name: 'metrics',
            message: 'Select metrics to compare:',
            choices: [
                'execution_time',
                'memory_usage',
                'throughput',
                'time_complexity',
                'memory_complexity'
            ],
            default: ['execution_time', 'throughput']
        });
    }

    const answers = await inquirer.prompt(questions);
    return { ...options, ...answers };
}

/**
 * Prompt for watch options
 */
async function promptForWatchOptions(options) {
    const questions = [];

    if (!options.dir) {
        questions.push({
            type: 'input',
            name: 'dir',
            message: 'Enter directory to watch:',
            default: path.join(process.cwd(), 'storage/logs'),
            validate: dir => fs.existsSync(dir) || 'Directory does not exist'
        });
    }

    if (!options.interval) {
        questions.push({
            type: 'input',
            name: 'interval',
            message: 'Enter watch interval (seconds):',
            default: '5',
            validate: value => !isNaN(value) || 'Please enter a number'
        });
    }

    const answers = await inquirer.prompt(questions);
    return { ...options, ...answers };
}

/**
 * Generate visualizations
 */
async function generateVisualizations(config) {
    const spinner = ora('Generating visualizations...').start();

    try {
        const visualizer = new PerformanceVisualizer(config.dir);
        await visualizer.generateCharts();

        spinner.succeed(chalk.green('Visualizations generated successfully'));
        console.log(chalk.blue('\nOutput location:'), config.output);
    } catch (error) {
        spinner.fail(chalk.red('Failed to generate visualizations'));
        throw error;
    }
}

/**
 * Analyze results
 */
async function analyzeResults(config) {
    const spinner = ora('Analyzing results...').start();

    try {
        const data = JSON.parse(fs.readFileSync(config.file));
        const visualizer = new PerformanceVisualizer(path.dirname(config.file));
        const analysis = visualizer.analyzePerfData(data.results);

        spinner.succeed(chalk.green('Analysis complete'));

        console.log('\nPerformance Analysis:');
        console.log(chalk.blue('\nExecution Time:'));
        console.log(`  Min: ${analysis.execution_time.min.toFixed(2)}ms`);
        console.log(`  Max: ${analysis.execution_time.max.toFixed(2)}ms`);
        console.log(`  Avg: ${analysis.execution_time.avg.toFixed(2)}ms`);

        console.log(chalk.blue('\nMemory Usage:'));
        console.log(`  Min: ${analysis.memory_usage.min.toFixed(2)}MB`);
        console.log(`  Max: ${analysis.memory_usage.max.toFixed(2)}MB`);
        console.log(`  Avg: ${analysis.memory_usage.avg.toFixed(2)}MB`);

        console.log(chalk.blue('\nThroughput:'));
        console.log(`  Min: ${analysis.throughput.min.toFixed(2)} items/s`);
        console.log(`  Max: ${analysis.throughput.max.toFixed(2)} items/s`);
        console.log(`  Avg: ${analysis.throughput.avg.toFixed(2)} items/s`);

        console.log(chalk.blue('\nComplexity Analysis:'));
        console.log(`  Time Complexity: ${analysis.scaling.time_complexity}`);
        console.log(`  Memory Complexity: ${analysis.scaling.memory_complexity}`);
    } catch (error) {
        spinner.fail(chalk.red('Analysis failed'));
        throw error;
    }
}

/**
 * Compare results
 */
async function compareResults(config) {
    const spinner = ora('Comparing results...').start();

    try {
        const results = config.files.map(file => ({
            name: path.basename(file, '.json'),
            data: JSON.parse(fs.readFileSync(file))
        }));

        spinner.succeed(chalk.green('Comparison complete'));

        console.log('\nComparison Results:');
        config.metrics.forEach(metric => {
            console.log(chalk.blue(`\n${metric.toUpperCase()}:`));
            results.forEach(result => {
                const value = result.data.results[metric];
                console.log(`  ${result.name}: ${typeof value === 'number' ? value.toFixed(2) : value}`);
            });
        });
    } catch (error) {
        spinner.fail(chalk.red('Comparison failed'));
        throw error;
    }
}

/**
 * Watch for new results
 */
async function watchResults(config) {
    console.log(chalk.blue(`Watching ${config.dir} for new results...`));
    console.log(chalk.gray(`Checking every ${config.interval} seconds`));

    let lastFiles = new Set(fs.readdirSync(config.dir));

    setInterval(() => {
        const currentFiles = new Set(fs.readdirSync(config.dir));
        const newFiles = [...currentFiles].filter(file => !lastFiles.has(file));

        if (newFiles.length > 0) {
            console.log(chalk.green('\nNew results detected:'));
            newFiles.forEach(file => console.log(`  ${file}`));

            const visualizer = new PerformanceVisualizer(config.dir);
            visualizer.generateCharts()
                .then(() => console.log(chalk.green('Visualizations updated')))
                .catch(error => console.error(chalk.red('Failed to update visualizations:'), error));
        }

        lastFiles = currentFiles;
    }, config.interval * 1000);
}

program.parse(process.argv);
