<?php

namespace Ajz\Anthropic\Monitoring\Scripts;

use Carbon\Carbon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class MaintenanceWindowManager extends Command
{
    protected static $defaultName = 'monitoring:maintenance-window';
    private const CONFIG_PATH = __DIR__ . '/../alertmanager/cost-alerts.yml';
    private const SILENCE_API_ENDPOINT = 'http://alertmanager:9093/api/v2/silences';

    protected function configure()
    {
        $this->setDescription('Manage AlertManager maintenance windows')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform (create/list/delete)')
            ->addOption('start', 's', InputOption::VALUE_OPTIONAL, 'Start time (ISO8601)')
            ->addOption('duration', 'd', InputOption::VALUE_OPTIONAL, 'Duration in hours')
            ->addOption('comment', 'c', InputOption::VALUE_OPTIONAL, 'Maintenance window comment')
            ->addOption('environment', 'e', InputOption::VALUE_OPTIONAL, 'Target environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getOption('action');

        switch ($action) {
            case 'create':
                return $this->createMaintenanceWindow($input, $output);
            case 'list':
                return $this->listMaintenanceWindows($output);
            case 'delete':
                return $this->deleteMaintenanceWindow($input, $output);
            default:
                $output->writeln('<error>Invalid action specified</error>');
                return Command::FAILURE;
        }
    }

    private function createMaintenanceWindow(InputInterface $input, OutputInterface $output): int
    {
        $start = $input->getOption('start')
            ? Carbon::parse($input->getOption('start'))
            : Carbon::now();

        $duration = (int) ($input->getOption('duration') ?? 1);
        $end = $start->copy()->addHours($duration);

        $silence = [
            'matchers' => [
                [
                    'name' => 'severity',
                    'value' => 'warning',
                    'isRegex' => false
                ]
            ],
            'startsAt' => $start->toIso8601String(),
            'endsAt' => $end->toIso8601String(),
            'comment' => $input->getOption('comment') ?? 'Scheduled maintenance',
            'createdBy' => 'maintenance-script'
        ];

        if ($env = $input->getOption('environment')) {
            $silence['matchers'][] = [
                'name' => 'environment',
                'value' => $env,
                'isRegex' => false
            ];
        }

        try {
            $response = $this->postToAlertManager($silence);
            $output->writeln(sprintf(
                '<info>Created maintenance window: %s</info>',
                $response['silenceId']
            ));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Failed to create maintenance window: %s</error>',
                $e->getMessage()
            ));
            return Command::FAILURE;
        }
    }

    private function listMaintenanceWindows(OutputInterface $output): int
    {
        try {
            $silences = $this->getFromAlertManager('/api/v2/silences');

            $output->writeln("\n<info>Active Maintenance Windows:</info>");
            $output->writeln(str_repeat('-', 80));

            foreach ($silences as $silence) {
                if ($silence['status']['state'] === 'active') {
                    $output->writeln(sprintf(
                        "ID: %s\nStart: %s\nEnd: %s\nComment: %s\n",
                        $silence['id'],
                        Carbon::parse($silence['startsAt'])->format('Y-m-d H:i:s'),
                        Carbon::parse($silence['endsAt'])->format('Y-m-d H:i:s'),
                        $silence['comment']
                    ));
                    $output->writeln(str_repeat('-', 80));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Failed to list maintenance windows: %s</error>',
                $e->getMessage()
            ));
            return Command::FAILURE;
        }
    }

    private function deleteMaintenanceWindow(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getOption('id');
        if (!$id) {
            $output->writeln('<error>Maintenance window ID is required</error>');
            return Command::FAILURE;
        }

        try {
            $this->deleteFromAlertManager("/api/v2/silence/$id");
            $output->writeln(sprintf(
                '<info>Deleted maintenance window: %s</info>',
                $id
            ));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Failed to delete maintenance window: %s</error>',
                $e->getMessage()
            ));
            return Command::FAILURE;
        }
    }

    private function postToAlertManager(array $data): array
    {
        $ch = curl_init(self::SILENCE_API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            throw new \RuntimeException("AlertManager API returned status $statusCode");
        }

        return json_decode($response, true);
    }

    private function getFromAlertManager(string $endpoint): array
    {
        $ch = curl_init(self::SILENCE_API_ENDPOINT . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            throw new \RuntimeException("AlertManager API returned status $statusCode");
        }

        return json_decode($response, true);
    }

    private function deleteFromAlertManager(string $endpoint): void
    {
        $ch = curl_init(self::SILENCE_API_ENDPOINT . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            throw new \RuntimeException("AlertManager API returned status $statusCode");
        }
    }

    private function updateAlertManagerConfig(array $config): void
    {
        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents(self::CONFIG_PATH, $yaml);
    }

    private function loadAlertManagerConfig(): array
    {
        return Yaml::parse(file_get_contents(self::CONFIG_PATH));
    }
}

// Register command if running from CLI
if (PHP_SAPI === 'cli') {
    $application = new \Symfony\Component\Console\Application();
    $application->add(new MaintenanceWindowManager());
    $application->run();
}
