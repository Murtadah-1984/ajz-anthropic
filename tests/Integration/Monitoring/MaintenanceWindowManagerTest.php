<?php

namespace Tests\Integration\Monitoring;

use Tests\TestCase;
use Carbon\Carbon;
use Ajz\Anthropic\Monitoring\Scripts\MaintenanceWindowManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MaintenanceWindowManagerTest extends TestCase
{
    private CommandTester $commandTester;
    private string $alertManagerUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Create command tester
        $application = new Application();
        $command = new MaintenanceWindowManager();
        $application->add($command);
        $this->commandTester = new CommandTester($command);

        // Set AlertManager test URL from env or use default
        $this->alertManagerUrl = env('ALERTMANAGER_TEST_URL', 'http://alertmanager:9093');

        // Clear existing maintenance windows
        $this->clearMaintenanceWindows();
    }

    public function test_can_create_maintenance_window()
    {
        // Arrange
        $start = Carbon::now()->addHour();
        $duration = 2;
        $comment = 'Test maintenance window';

        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'create',
            '--start' => $start->toIso8601String(),
            '--duration' => $duration,
            '--comment' => $comment,
            '--environment' => 'test'
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Created maintenance window', $output);

        // Verify in AlertManager
        $silences = $this->getActiveSilences();
        $this->assertCount(1, $silences);
        $silence = $silences[0];

        $this->assertEquals($comment, $silence['comment']);
        $this->assertEquals(
            $start->startOfMinute()->toIso8601String(),
            Carbon::parse($silence['startsAt'])->startOfMinute()->toIso8601String()
        );
        $this->assertEquals(
            $start->addHours($duration)->startOfMinute()->toIso8601String(),
            Carbon::parse($silence['endsAt'])->startOfMinute()->toIso8601String()
        );
    }

    public function test_can_list_maintenance_windows()
    {
        // Arrange
        $this->createTestMaintenanceWindow('Test window 1');
        $this->createTestMaintenanceWindow('Test window 2');

        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'list'
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Test window 1', $output);
        $this->assertStringContainsString('Test window 2', $output);
    }

    public function test_can_delete_maintenance_window()
    {
        // Arrange
        $silenceId = $this->createTestMaintenanceWindow('Window to delete');

        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'delete',
            '--id' => $silenceId
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Deleted maintenance window: $silenceId", $output);

        // Verify in AlertManager
        $silences = $this->getActiveSilences();
        $this->assertCount(0, $silences);
    }

    public function test_handles_invalid_start_time()
    {
        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'create',
            '--start' => 'invalid-date',
            '--duration' => 1,
            '--comment' => 'Test'
        ]);

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to create maintenance window', $output);
    }

    public function test_handles_invalid_duration()
    {
        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'create',
            '--duration' => 'invalid',
            '--comment' => 'Test'
        ]);

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to create maintenance window', $output);
    }

    public function test_handles_alertmanager_unavailable()
    {
        // Arrange
        $this->alertManagerUrl = 'http://invalid-host:9093';

        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'list'
        ]);

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to list maintenance windows', $output);
    }

    public function test_respects_environment_filter()
    {
        // Arrange
        $this->createTestMaintenanceWindow('Prod window', 'prod');
        $this->createTestMaintenanceWindow('Dev window', 'dev');

        // Act
        $exitCode = $this->commandTester->execute([
            '--action' => 'list',
            '--environment' => 'prod'
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Prod window', $output);
        $this->assertStringNotContainsString('Dev window', $output);
    }

    public function test_handles_concurrent_operations()
    {
        // Arrange
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $processes[] = $this->startAsyncProcess([
                '--action' => 'create',
                '--comment' => "Concurrent window $i"
            ]);
        }

        // Act
        foreach ($processes as $process) {
            $process->wait();
        }

        // Assert
        $silences = $this->getActiveSilences();
        $this->assertCount(5, $silences);

        $comments = array_column($silences, 'comment');
        for ($i = 0; $i < 5; $i++) {
            $this->assertContains("Concurrent window $i", $comments);
        }
    }

    private function createTestMaintenanceWindow(string $comment, string $environment = 'test'): string
    {
        $data = [
            'matchers' => [
                [
                    'name' => 'severity',
                    'value' => 'warning',
                    'isRegex' => false
                ],
                [
                    'name' => 'environment',
                    'value' => $environment,
                    'isRegex' => false
                ]
            ],
            'startsAt' => Carbon::now()->toIso8601String(),
            'endsAt' => Carbon::now()->addHours(1)->toIso8601String(),
            'comment' => $comment,
            'createdBy' => 'test'
        ];

        $response = $this->postToAlertManager('/api/v2/silences', $data);
        return $response['silenceId'];
    }

    private function getActiveSilences(): array
    {
        $response = $this->getFromAlertManager('/api/v2/silences');
        return array_filter($response, function ($silence) {
            return $silence['status']['state'] === 'active';
        });
    }

    private function clearMaintenanceWindows(): void
    {
        $silences = $this->getActiveSilences();
        foreach ($silences as $silence) {
            $this->deleteFromAlertManager("/api/v2/silence/{$silence['id']}");
        }
    }

    private function postToAlertManager(string $endpoint, array $data): array
    {
        $ch = curl_init("{$this->alertManagerUrl}{$endpoint}");
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
        $ch = curl_init("{$this->alertManagerUrl}{$endpoint}");
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
        $ch = curl_init("{$this->alertManagerUrl}{$endpoint}");
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

    private function startAsyncProcess(array $arguments): Process
    {
        $command = [
            PHP_BINARY,
            'artisan',
            'monitoring:maintenance-window'
        ];

        foreach ($arguments as $key => $value) {
            $command[] = $key;
            if ($value !== null) {
                $command[] = $value;
            }
        }

        $process = new Process($command);
        $process->start();
        return $process;
    }
}
