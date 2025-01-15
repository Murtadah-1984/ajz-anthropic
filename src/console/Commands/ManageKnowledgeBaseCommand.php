<?php

namespace Ajz\Anthropic\Commands;

use Illuminate\Console\Command;
use Ajz\Anthropic\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\File;

class ManageKnowledgeBaseCommand extends Command
{
    protected $signature = 'anthropic:knowledge
                          {action : Action to perform (import|export|list|search|train)}
                          {--collection=* : Collection names or IDs}
                          {--format=json : Format for import/export (json|markdown|csv)}
                          {--path= : Path for import/export}
                          {--query= : Search query}
                          {--type= : Entry type filter}';

    protected $description = 'Manage AI agent knowledge base';

    protected KnowledgeBaseService $knowledgeService;

    public function __construct(KnowledgeBaseService $knowledgeService)
    {
        parent::__construct();
        $this->knowledgeService = $knowledgeService;
    }

    public function handle()
    {
        $action = $this->argument('action');

        try {
            match ($action) {
                'import' => $this->importKnowledge(),
                'export' => $this->exportKnowledge(),
                'list' => $this->listKnowledge(),
                'search' => $this->searchKnowledge(),
                'train' => $this->trainKnowledge(),
                default => $this->error("Unknown action: {$action}")
            };

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to {$action} knowledge: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function importKnowledge()
    {
        $path = $this->option('path') ?? $this->ask('Enter path to knowledge files');
        $format = $this->option('format');

        if (!File::exists($path)) {
            throw new \RuntimeException("Path not found: {$path}");
        }

        $this->info("Importing knowledge from {$path}...");
        $bar = $this->output->createProgressBar(
            File::isDirectory($path) ? count(File::files($path)) : 1
        );

        if (File::isDirectory($path)) {
            foreach (File::files($path) as $file) {
                $this->importFile($file, $format);
                $bar->advance();
            }
        } else {
            $this->importFile($path, $format);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import completed successfully!');
    }

    protected function exportKnowledge()
    {
        $path = $this->option('path') ?? $this->ask('Enter export path');
        $collections = $this->option('collection');
        $format = $this->option('format');

        $this->info("Exporting knowledge to {$path}...");

        $entries = $this->knowledgeService->getEntries($collections);

        match ($format) {
            'json' => $this->exportJson($entries, $path),
            'markdown' => $this->exportMarkdown($entries, $path),
            'csv' => $this->exportCsv($entries, $path),
            default => throw new \RuntimeException("Unsupported format: {$format}")
        };

        $this->info('Export completed successfully!');
    }

    protected function listKnowledge()
    {
        $collections = $this->knowledgeService->listCollections();

        foreach ($collections as $collection) {
            $this->info("\nCollection: {$collection->name}");
            $this->line('----------------------------------------');

            $entries = $collection->entries;
            $this->table(
                ['ID', 'Title', 'Type', 'Created At'],
                $entries->map(fn($entry) => [
                    $entry->id,
                    $entry->title,
                    $entry->type,
                    $entry->created_at->format('Y-m-d H:i:s')
                ])
            );
        }
    }

    protected function searchKnowledge()
    {
        $query = $this->option('query') ?? $this->ask('Enter search query');
        $type = $this->option('type');

        $results = $this->knowledgeService->search($query, [
            'type' => $type
        ]);

        if ($results->isEmpty()) {
            $this->warn('No results found.');
            return;
        }

        $this->table(
            ['ID', 'Title', 'Collection', 'Relevance'],
            $results->map(fn($entry) => [
                $entry->id,
                $entry->title,
                $entry->collection->name,
                round($entry->relevance, 2)
            ])
        );
    }

    protected function trainKnowledge()
    {
        $collections = $this->option('collection');

        $this->info('Training knowledge base embeddings...');
        $progress = $this->output->createProgressBar();

        $this->knowledgeService->trainEmbeddings(
            $collections,
            function ($current, $total) use ($progress) {
                $progress->setMaxSteps($total);
                $progress->setProgress($current);
            }
        );

        $progress->finish();
        $this->newLine();
        $this->info('Training completed successfully!');
    }

    protected function importFile($path, $format)
    {
        $content = File::get($path);

        $data = match ($format) {
            'json' => json_decode($content, true),
            'markdown' => $this->parseMarkdown($content),
            'csv' => $this->parseCsv($content),
            default => throw new \RuntimeException("Unsupported format: {$format}")
        };

        foreach ($data as $entry) {
            $this->knowledgeService->addEntry($entry);
        }
    }

    protected function parseMarkdown($content)
    {
        // Implement markdown parsing logic
        $entries = [];
        $sections = preg_split('/^# /m', $content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sections as $section) {
            if (empty(trim($section))) continue;

            $lines = explode("\n", $section);
            $title = trim(array_shift($lines));
            $content = trim(implode("\n", $lines));

            $entries[] = [
                'title' => $title,
                'content' => $content,
                'type' => 'text'
            ];
        }

        return $entries;
    }

    protected function parseCsv($content)
    {
        // Implement CSV parsing logic
        $rows = str_getcsv($content, "\n");
        $headers = str_getcsv(array_shift($rows));
        $entries = [];

        foreach ($rows as $row) {
            $data = array_combine($headers, str_getcsv($row));
            $entries[] = [
                'title' => $data['title'],
                'content' => $data['content'],
                'type' => $data['type'] ?? 'text',
                'metadata' => json_decode($data['metadata'] ?? '{}', true)
            ];
        }

        return $entries;
    }
}
