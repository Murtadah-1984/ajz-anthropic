<?php

namespace Ajz\Anthropic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Ajz\Anthropic\Services\Organization\OrganizationManagementService;

class MonitorUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anthropic:monitor:usage
                          {--org= : Organization ID to monitor}
                          {--period=daily : Period to analyze (daily/weekly/monthly)}
                          {--from= : Start date (YYYY-MM-DD)}
                          {--to= : End date (YYYY-MM-DD)}
                          {--export= : Export format (json/csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Anthropic API usage and costs';

    /**
     * The organization management service instance.
     *
     * @var OrganizationManagementService
     */
    protected OrganizationManagementService $organizationService;

    /**
     * Create a new command instance.
     *
     * @param OrganizationManagementService $organizationService
     */
    public function __construct(OrganizationManagementService $organizationService)
    {
        parent::__construct();
        $this->organizationService = $organizationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $orgId = $this->getOrganizationId();
            $period = $this->option('period');
            $dateRange = $this->getDateRange($period);

            $usage = $this->getUsageData($orgId, $dateRange);

            if (empty($usage)) {
                $this->warn('No usage data found for the specified period.');
                return 0;
            }

            $this->displayUsage($usage, $period);

            if ($exportFormat = $this->option('export')) {
                $this->exportUsage($usage, $exportFormat);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to monitor usage: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Get organization ID from option or prompt.
     *
     * @return string
     */
    protected function getOrganizationId(): string
    {
        if ($orgId = $this->option('org')) {
            return $orgId;
        }

        $organizations = $this->organizationService->listOrganizations();

        if (empty($organizations)) {
            throw new \RuntimeException('No organizations found.');
        }

        $choices = collect($organizations)->mapWithKeys(function ($org) {
            return [$org['id'] => "{$org['name']} ({$org['id']})"];
        })->toArray();

        return $this->choice(
            'Select organization to monitor',
            $choices,
            null,
            null,
            false
        );
    }

    /**
     * Get date range based on period.
     *
     * @param string $period
     * @return array
     */
    protected function getDateRange(string $period): array
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : null;

        if ($from && $to) {
            return ['from' => $from, 'to' => $to];
        }

        switch ($period) {
            case 'daily':
                $from = Carbon::today();
                $to = Carbon::tomorrow();
                break;
            case 'weekly':
                $from = Carbon::now()->startOfWeek();
                $to = Carbon::now()->endOfWeek();
                break;
            case 'monthly':
                $from = Carbon::now()->startOfMonth();
                $to = Carbon::now()->endOfMonth();
                break;
            default:
                throw new \InvalidArgumentException("Invalid period: $period");
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Get usage data for the specified organization and date range.
     *
     * @param string $orgId
     * @param array $dateRange
     * @return array
     */
    protected function getUsageData(string $orgId, array $dateRange): array
    {
        return $this->organizationService->getUsageStats($orgId, [
            'from' => $dateRange['from']->toISOString(),
            'to' => $dateRange['to']->toISOString(),
        ]);
    }

    /**
     * Display usage information.
     *
     * @param array $usage
     * @param string $period
     * @return void
     */
    protected function displayUsage(array $usage, string $period): void
    {
        $this->info("\nUsage Statistics ($period)");
        $this->line('----------------------------------------');

        // Display request statistics
        $this->displayRequestStats($usage);

        // Display token usage by model
        $this->displayTokenUsage($usage);

        // Display cost breakdown
        $this->displayCostBreakdown($usage);

        // Display rate limit status
        $this->displayRateLimitStatus($usage);
    }

    /**
     * Display request statistics.
     *
     * @param array $usage
     * @return void
     */
    protected function displayRequestStats(array $usage): void
    {
        $this->info("\nRequest Statistics:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $usage['total_requests'] ?? 0],
                ['Successful Requests', $usage['successful_requests'] ?? 0],
                ['Failed Requests', $usage['failed_requests'] ?? 0],
                ['Average Response Time', ($usage['avg_response_time'] ?? 0) . 'ms'],
            ]
        );
    }

    /**
     * Display token usage by model.
     *
     * @param array $usage
     * @return void
     */
    protected function displayTokenUsage(array $usage): void
    {
        $this->info("\nToken Usage by Model:");
        $rows = [];
        foreach ($usage['models'] ?? [] as $model => $stats) {
            $rows[] = [
                'Model' => $model,
                'Input Tokens' => $stats['input_tokens'] ?? 0,
                'Output Tokens' => $stats['output_tokens'] ?? 0,
                'Total Tokens' => $stats['total_tokens'] ?? 0,
            ];
        }
        $this->table(['Model', 'Input Tokens', 'Output Tokens', 'Total Tokens'], $rows);
    }

    /**
     * Display cost breakdown.
     *
     * @param array $usage
     * @return void
     */
    protected function displayCostBreakdown(array $usage): void
    {
        $this->info("\nCost Breakdown:");
        $rows = [];
        foreach ($usage['costs'] ?? [] as $model => $cost) {
            $rows[] = [
                'Model' => $model,
                'Cost' => '$' . number_format($cost, 4),
            ];
        }
        $rows[] = ['Total', '$' . number_format($usage['total_cost'] ?? 0, 4)];
        $this->table(['Category', 'Amount'], $rows);
    }

    /**
     * Display rate limit status.
     *
     * @param array $usage
     * @return void
     */
    protected function displayRateLimitStatus(array $usage): void
    {
        $limits = $usage['rate_limits'] ?? [];
        if (!empty($limits)) {
            $this->info("\nRate Limit Status:");
            $this->table(
                ['Limit Type', 'Used', 'Remaining', 'Reset At'],
                collect($limits)->map(function ($limit) {
                    return [
                        'Type' => $limit['type'],
                        'Used' => $limit['used'],
                        'Remaining' => $limit['remaining'],
                        'Reset At' => Carbon::parse($limit['reset_at'])->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );
        }
    }

    /**
     * Export usage data to file.
     *
     * @param array $usage
     * @param string $format
     * @return void
     */
    protected function exportUsage(array $usage, string $format): void
    {
        $filename = 'anthropic_usage_' . Carbon::now()->format('Y-m-d_His');

        switch ($format) {
            case 'json':
                $content = json_encode($usage, JSON_PRETTY_PRINT);
                $filename .= '.json';
                break;
            case 'csv':
                $content = $this->convertToCsv($usage);
                $filename .= '.csv';
                break;
            default:
                throw new \InvalidArgumentException("Unsupported export format: $format");
        }

        file_put_contents($filename, $content);
        $this->info("\nUsage data exported to: $filename");
    }

    /**
     * Convert usage data to CSV format.
     *
     * @param array $usage
     * @return string
     */
    protected function convertToCsv(array $usage): string
    {
        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, ['Category', 'Metric', 'Value']);

        // Write request statistics
        fputcsv($output, ['Requests', 'Total', $usage['total_requests'] ?? 0]);
        fputcsv($output, ['Requests', 'Successful', $usage['successful_requests'] ?? 0]);
        fputcsv($output, ['Requests', 'Failed', $usage['failed_requests'] ?? 0]);

        // Write token usage
        foreach ($usage['models'] ?? [] as $model => $stats) {
            fputcsv($output, ['Tokens', "$model Input", $stats['input_tokens'] ?? 0]);
            fputcsv($output, ['Tokens', "$model Output", $stats['output_tokens'] ?? 0]);
            fputcsv($output, ['Tokens', "$model Total", $stats['total_tokens'] ?? 0]);
        }

        // Write costs
        foreach ($usage['costs'] ?? [] as $model => $cost) {
            fputcsv($output, ['Cost', $model, $cost]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
