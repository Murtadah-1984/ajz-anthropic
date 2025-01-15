<?php

namespace Ajz\Anthropic\Commands;

use Illuminate\Console\Command;
use Ajz\Anthropic\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class TrainDocumentationKnowledgeCommand extends Command
{
    protected $signature = 'anthropic:train-docs
                          {path? : Path to documentation files}
                          {--type=* : Types of documentation to process}
                          {--recursive : Process directories recursively}
                          {--force : Force reprocess existing documentation}';

    protected $description = 'Train AI with technical documentation';

    protected KnowledgeBaseService $knowledgeService;

    public function __construct(KnowledgeBaseService $knowledgeService)
    {
        parent::__construct();
        $this->knowledgeService = $knowledgeService;
    }

    public function handle()
    {
        try {
            $path = $this->argument('path') ?? $this->ask('Enter path to documentation files');

            if (!File::exists($path)) {
                $this->error("Path not found: {$path}");
                return Command::FAILURE;
            }

            $this->info('Analyzing documentation structure...');
            $files = $this->getDocumentationFiles($path);

            $this->info(sprintf('Found %d documentation files', count($files)));
            $bar = $this->output->createProgressBar(count($files));

            foreach ($files as $file) {
                $this->processDocumentationFile($file);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info('Documentation training completed successfully!');

            // Display statistics
            $this->displayTrainingStats();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Training failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function getDocumentationFiles(string $path): array
    {
        $finder = new Finder();
        $finder->files()->in($path);

        if ($this->option('recursive')) {
            $finder->recursive();
        }

        $types = $this->option('type');
        if (!empty($types)) {
            $finder->name($this->getFilePatterns($types));
        }

        return iterator_to_array($finder, false);
    }

    protected function getFilePatterns(array $types): array
    {
        $patterns = [];
        foreach ($types as $type) {
            $patterns = array_merge($patterns, match ($type) {
                'markdown' => ['*.md', '*.markdown'],
                'api' => ['*.yaml', '*.json'],
                'code' => ['*.php', '*.js', '*.py', '*.java'],
                'config' => ['*.config.*', '*.conf', '*.ini'],
                default => ["*.{$type}"]
            });
        }
        return $patterns;
    }

    protected function processDocumentationFile(\SplFileInfo $file)
    {
        $content = File::get($file->getRealPath());
        $type = $this->determineDocumentationType($file);

        $entry = [
            'title' => $this->generateTitle($file),
            'content' => $this->parseContent($content, $type),
            'type' => $type,
            'metadata' => $this->extractMetadata($file, $content)
        ];

        // Add to knowledge base
        $this->knowledgeService->addEntry(array_merge(
            $entry,
            ['collection_id' => $this->getDocumentationCollectionId()]
        ));
    }

    protected function determineDocumentationType(\SplFileInfo $file): string
    {
        return match ($file->getExtension()) {
            'md', 'markdown' => 'markdown',
            'yaml', 'json' => 'api',
            'php', 'js', 'py', 'java' => 'code',
            'config', 'conf', 'ini' => 'configuration',
            default => 'text'
        };
    }

    protected function generateTitle(\SplFileInfo $file): string
    {
        $basename = $file->getBasename('.' . $file->getExtension());
        return str($basename)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }

    protected function parseContent(string $content, string $type): string
    {
        return match ($type) {
            'markdown' => $this->parseMarkdown($content),
            'api' => $this->parseApiDoc($content),
            'code' => $this->parseCodeDoc($content),
            default => $content
        };
    }

    protected function extractMetadata(\SplFileInfo $file, string $content): array
    {
        return [
            'file_path' => $file->getRealPath(),
            'file_size' => $file->getSize(),
            'last_modified' => $file->getMTime(),
            'language' => $this->detectLanguage($file),
            'framework' => $this->detectFramework($content),
            'processed_at' => now()->toDateTimeString()
        ];
    }

    protected function detectLanguage(\SplFileInfo $file): ?string
    {
        return match ($file->getExtension()) {
            'php' => 'PHP',
            'js' => 'JavaScript',
            'py' => 'Python',
            'java' => 'Java',
            default => null
        };
    }

    protected function detectFramework(string $content): ?string
    {
        $frameworks = [
            'Laravel' => ['laravel', 'artisan', 'eloquent'],
            'React' => ['react', 'useState', 'useEffect'],
            'Vue' => ['vue', 'v-model', 'v-for'],
            'Django' => ['django', 'urls.py', 'views.py'],
            'Spring' => ['@SpringBootApplication', '@Autowired', '@Controller']
        ];

        foreach ($frameworks as $framework => $patterns) {
            if (str_contains_all($content, $patterns)) {
                return $framework;
            }
        }

        return null;
    }

    protected function getDocumentationCollectionId(): int
    {
        $collection = \Ajz\Anthropic\Models\KnowledgeCollection::firstOrCreate(
            ['slug' => 'technical-documentation'],
            [
                'name' => 'Technical Documentation',
                'description' => 'Knowledge base for technical documentation and code examples'
            ]
        );

        return $collection->id;
    }

    protected function displayTrainingStats(): void
    {
        $stats = $this->knowledgeService->getCollectionStats(
            $this->getDocumentationCollectionId()
        );

        $this->info("\nTraining Statistics:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', $stats['total_entries']],
                ['Document Types', implode(', ', $stats['entry_types'])],
                ['Total Content Size', $this->formatBytes($stats['content_size'])],
                ['Average Entry Size', $this->formatBytes($stats['avg_entry_size'])],
                ['Last Updated', $stats['last_updated']]
            ]
        );
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $level = 0;

        while ($bytes >= 1024 && $level < count($units) - 1) {
            $bytes /= 1024;
            $level++;
        }

        return round($bytes, 2) . ' ' . $units[$level];
    }
}
