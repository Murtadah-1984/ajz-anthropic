<?php

namespace Ajz\Anthropic\Services\Documentation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class DocumentationManager
{
    /**
     * Base path for documentation output.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Documentation configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new documentation manager instance.
     */
    public function __construct()
    {
        $this->config = config('anthropic.docs');
        $this->basePath = $this->config['generation']['output_path'];
    }

    /**
     * Generate all documentation.
     *
     * @return bool
     */
    public function generate(): bool
    {
        try {
            if (!$this->config['generation']['enabled']) {
                return false;
            }

            // Clean output directory if configured
            if ($this->config['generation']['clean_output']) {
                $this->cleanOutputDirectory();
            }

            // Generate different types of documentation
            $this->generatePhpDocs();
            $this->generateApiDocs();
            $this->generateConfigDocs();
            $this->generateExamples();

            // Run validation if enabled
            if ($this->config['testing']['enabled']) {
                $this->validateDocumentation();
            }

            return true;
        } catch (Throwable $e) {
            $this->logError('Documentation generation failed', $e);
            return false;
        }
    }

    /**
     * Generate PHPDoc documentation.
     *
     * @return void
     */
    protected function generatePhpDocs(): void
    {
        $sourceDir = base_path('src');
        $outputDir = $this->basePath . '/php';

        // Create output directory
        File::makeDirectory($outputDir, 0755, true, true);

        // Get all PHP files recursively
        $files = File::allFiles($sourceDir);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->getClassFromFile($file->getPathname());
            if (!$class) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $docs = $this->generateClassDocs($reflection);

            // Save documentation
            $outputPath = $outputDir . '/' . $reflection->getShortName() . '.md';
            File::put($outputPath, $docs);
        }
    }

    /**
     * Generate API documentation.
     *
     * @return void
     */
    protected function generateApiDocs(): void
    {
        if (!$this->config['api']['enabled']) {
            return;
        }

        $outputDir = $this->config['api']['output_path'];
        File::makeDirectory($outputDir, 0755, true, true);

        // Generate OpenAPI specification
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->config['api']['title'],
                'version' => $this->config['api']['version'],
            ],
            'servers' => $this->config['api']['servers'],
            'security' => [
                ['api_key' => []],
                ['bearer' => []],
            ],
            'paths' => $this->generateApiPaths(),
            'components' => [
                'securitySchemes' => $this->config['api']['security_schemes'],
                'schemas' => $this->generateApiSchemas(),
            ],
            'tags' => array_map(fn ($tag, $desc) => [
                'name' => $tag,
                'description' => $desc,
            ], array_keys($this->config['api']['tags']), $this->config['api']['tags']),
        ];

        // Save OpenAPI specification
        File::put($outputDir . '/openapi.json', json_encode($spec, JSON_PRETTY_PRINT));
    }

    /**
     * Generate configuration documentation.
     *
     * @return void
     */
    protected function generateConfigDocs(): void
    {
        if (!$this->config['config']['enabled']) {
            return;
        }

        $outputDir = $this->config['config']['output_path'];
        File::makeDirectory($outputDir, 0755, true, true);

        // Generate documentation for each config section
        foreach ($this->config['config']['sections'] as $section => $title) {
            $content = $this->generateConfigSection($section, $title);
            File::put($outputDir . "/{$section}.md", $content);
        }

        // Generate environment variables documentation if enabled
        if ($this->config['config']['include_env']) {
            $this->generateEnvDocs($outputDir);
        }
    }

    /**
     * Generate code examples.
     *
     * @return void
     */
    protected function generateExamples(): void
    {
        $templatesPath = $this->config['examples']['templates']['path'];
        $outputDir = $this->basePath . '/examples';

        File::makeDirectory($outputDir, 0755, true, true);

        foreach ($this->config['examples']['languages'] as $language) {
            $pattern = str_replace('{lang}', $language, $this->config['examples']['templates']['extension']);
            $templates = File::glob($templatesPath . '/*' . $pattern);

            foreach ($templates as $template) {
                $content = File::get($template);
                $output = $this->processExampleTemplate($content, $language);

                $outputFile = $outputDir . '/' . basename($template, $pattern) . '.' . $language;
                File::put($outputFile, $output);
            }
        }
    }

    /**
     * Validate generated documentation.
     *
     * @return array
     */
    public function validateDocumentation(): array
    {
        $results = [
            'passed' => true,
            'issues' => [],
        ];

        // Validate code examples
        if ($this->config['testing']['test_examples']) {
            $exampleResults = $this->validateExamples();
            if (!$exampleResults['passed']) {
                $results['passed'] = false;
                $results['issues'] = array_merge($results['issues'], $exampleResults['issues']);
            }
        }

        // Validate URLs
        if ($this->config['testing']['validate_urls']) {
            $urlResults = $this->validateUrls();
            if (!$urlResults['passed']) {
                $results['passed'] = false;
                $results['issues'] = array_merge($results['issues'], $urlResults['issues']);
            }
        }

        // Validate internal links
        if ($this->config['testing']['check_internal_links']) {
            $linkResults = $this->validateInternalLinks();
            if (!$linkResults['passed']) {
                $results['passed'] = false;
                $results['issues'] = array_merge($results['issues'], $linkResults['issues']);
            }
        }

        // Validate code blocks
        if ($this->config['testing']['validate_code_blocks']) {
            $codeResults = $this->validateCodeBlocks();
            if (!$codeResults['passed']) {
                $results['passed'] = false;
                $results['issues'] = array_merge($results['issues'], $codeResults['issues']);
            }
        }

        return $results;
    }

    /**
     * Get documentation metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $metrics = [
            'coverage' => 0,
            'freshness' => 0,
            'completeness' => 0,
            'accuracy' => 0,
        ];

        if (!$this->config['monitoring']['enabled']) {
            return $metrics;
        }

        // Calculate coverage
        if ($this->config['monitoring']['metrics']['coverage']) {
            $metrics['coverage'] = $this->calculateCoverage();
        }

        // Calculate freshness
        if ($this->config['monitoring']['metrics']['freshness']) {
            $metrics['freshness'] = $this->calculateFreshness();
        }

        // Calculate completeness
        if ($this->config['monitoring']['metrics']['completeness']) {
            $metrics['completeness'] = $this->calculateCompleteness();
        }

        // Calculate accuracy
        if ($this->config['monitoring']['metrics']['accuracy']) {
            $metrics['accuracy'] = $this->calculateAccuracy();
        }

        return $metrics;
    }

    /**
     * Clean the output directory.
     *
     * @return void
     */
    protected function cleanOutputDirectory(): void
    {
        if (File::exists($this->basePath)) {
            File::deleteDirectory($this->basePath);
        }
        File::makeDirectory($this->basePath, 0755, true);
    }

    /**
     * Get class name from file path.
     *
     * @param string $path
     * @return string|null
     */
    protected function getClassFromFile(string $path): ?string
    {
        $contents = File::get($path);
        if (preg_match('/namespace\s+(.+?);.*?class\s+(\w+)/s', $contents, $matches)) {
            return $matches[1] . '\\' . $matches[2];
        }
        return null;
    }

    /**
     * Generate documentation for a class.
     *
     * @param ReflectionClass $class
     * @return string
     */
    protected function generateClassDocs(ReflectionClass $class): string
    {
        $docs = "# {$class->getShortName()}\n\n";

        // Add class description
        $classDoc = $class->getDocComment();
        if ($classDoc) {
            $docs .= $this->formatDocBlock($classDoc) . "\n\n";
        }

        // Add methods
        $docs .= "## Methods\n\n";
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $docs .= $this->generateMethodDocs($method);
        }

        return $docs;
    }

    /**
     * Generate documentation for a method.
     *
     * @param ReflectionMethod $method
     * @return string
     */
    protected function generateMethodDocs(ReflectionMethod $method): string
    {
        $docs = "### {$method->getName()}\n\n";

        $methodDoc = $method->getDocComment();
        if ($methodDoc) {
            $docs .= $this->formatDocBlock($methodDoc) . "\n\n";
        }

        return $docs;
    }

    /**
     * Format a DocBlock comment.
     *
     * @param string $docBlock
     * @return string
     */
    protected function formatDocBlock(string $docBlock): string
    {
        // Remove comment markers
        $text = preg_replace('/^\s*\/\*\*|\s*\*\/|\s*\* ?/m', '', $docBlock);
        return trim($text);
    }

    /**
     * Log an error.
     *
     * @param string $message
     * @param Throwable $exception
     * @return void
     */
    protected function logError(string $message, Throwable $exception): void
    {
        Log::error($message, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Calculate documentation coverage.
     *
     * @return float
     */
    protected function calculateCoverage(): float
    {
        // Implementation details...
        return 0.0;
    }

    /**
     * Calculate documentation freshness.
     *
     * @return float
     */
    protected function calculateFreshness(): float
    {
        // Implementation details...
        return 0.0;
    }

    /**
     * Calculate documentation completeness.
     *
     * @return float
     */
    protected function calculateCompleteness(): float
    {
        // Implementation details...
        return 0.0;
    }

    /**
     * Calculate documentation accuracy.
     *
     * @return float
     */
    protected function calculateAccuracy(): float
    {
        // Implementation details...
        return 0.0;
    }

    /**
     * Generate API paths documentation.
     *
     * @return array
     */
    protected function generateApiPaths(): array
    {
        // Implementation details...
        return [];
    }

    /**
     * Generate API schemas documentation.
     *
     * @return array
     */
    protected function generateApiSchemas(): array
    {
        // Implementation details...
        return [];
    }

    /**
     * Generate configuration section documentation.
     *
     * @param string $section
     * @param string $title
     * @return string
     */
    protected function generateConfigSection(string $section, string $title): string
    {
        // Implementation details...
        return '';
    }

    /**
     * Generate environment variables documentation.
     *
     * @param string $outputDir
     * @return void
     */
    protected function generateEnvDocs(string $outputDir): void
    {
        // Implementation details...
    }

    /**
     * Process example template.
     *
     * @param string $content
     * @param string $language
     * @return string
     */
    protected function processExampleTemplate(string $content, string $language): string
    {
        // Implementation details...
        return '';
    }

    /**
     * Validate code examples.
     *
     * @return array
     */
    protected function validateExamples(): array
    {
        // Implementation details...
        return ['passed' => true, 'issues' => []];
    }

    /**
     * Validate URLs in documentation.
     *
     * @return array
     */
    protected function validateUrls(): array
    {
        // Implementation details...
        return ['passed' => true, 'issues' => []];
    }

    /**
     * Validate internal links.
     *
     * @return array
     */
    protected function validateInternalLinks(): array
    {
        // Implementation details...
        return ['passed' => true, 'issues' => []];
    }

    /**
     * Validate code blocks.
     *
     * @return array
     */
    protected function validateCodeBlocks(): array
    {
        // Implementation details...
        return ['passed' => true, 'issues' => []];
    }
}
