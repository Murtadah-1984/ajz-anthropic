<?php

namespace App\AIAgents\Specialized;

use App\AIAgents\PermanentAgent;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class DeveloperAgent extends PermanentAgent
{
    protected string $role = 'developer';

    protected function initializeCapabilities(): void
    {
        $this->capabilities = [
            'primary_skills' => [
                'code_generation',
                'debugging',
                'code_review',
                'refactoring',
                'optimization',
                'testing'
            ],
            'knowledge_domains' => [
                'software_development',
                'design_patterns',
                'algorithms',
                'data_structures',
                'best_practices',
                'security'
            ],
            'supported_languages' => [
                'php' => ['laravel', 'symfony', 'wordpress'],
                'javascript' => ['react', 'vue', 'node'],
                'python' => ['django', 'flask', 'fastapi'],
                'database' => ['mysql', 'postgresql', 'mongodb']
            ],
            'specializations' => [
                'web_development',
                'api_design',
                'database_optimization',
                'security_implementation'
            ]
        ];

        $this->configuration = [
            'code_style' => 'PSR-12',
            'documentation_level' => 'comprehensive',
            'testing_requirements' => 'required',
            'security_checks' => 'mandatory',
            'performance_consideration' => 'high',
            'response_format' => [
                'explanation' => true,
                'code_examples' => true,
                'best_practices' => true,
                'considerations' => true
            ]
        ];
    }

    protected function getSpecializedPrompt(): string
    {
        return <<<EOT
You are an expert software developer with deep knowledge in:
{$this->formatKnowledgeDomains()}

When providing solutions:
1. Always follow {$this->configuration['code_style']} coding standards
2. Include comprehensive documentation and comments
3. Consider security implications and best practices
4. Provide test examples where applicable
5. Explain your implementation choices
6. Consider performance implications

For code generation:
- Start with a high-level overview
- Break down complex solutions into manageable parts
- Include error handling and validation
- Add type hints and return types
- Follow SOLID principles
- Consider scalability and maintainability

Your responses should include:
- Detailed explanations
- Code examples
- Best practices considerations
- Potential pitfalls and how to avoid them
- Performance considerations
- Security considerations
EOT;
    }

    public function handleRequest(Conversation $conversation, string $input): array
    {
        try {
            // Analyze code-specific requirements
            $analysis = $this->analyzeCodeRequest($input);

            // Update context with code-specific information
            $this->context['code_analysis'] = $analysis;

            // Generate response with specialized handling
            $response = parent::handleRequest($conversation, $input);

            // Add code-specific metadata
            $response['metadata'] = array_merge(
                $response['metadata'] ?? [],
                [
                    'code_analysis' => $analysis,
                    'language_specific_tips' => $this->getLanguageSpecificTips($analysis['language'] ?? null),
                    'security_considerations' => $this->getSecurityConsiderations($analysis['context'] ?? '')
                ]
            );

            return $response;
        } catch (\Exception $e) {
            Log::error('Developer Agent Error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'input' => $input
            ]);

            throw $e;
        }
    }

    protected function analyzeCodeRequest(string $input): array
    {
        // Detect programming language
        $language = $this->detectProgrammingLanguage($input);

        // Identify request type (implementation, debug, review, etc.)
        $requestType = $this->identifyRequestType($input);

        // Detect frameworks or specific technologies
        $technologies = $this->detectTechnologies($input);

        return [
            'language' => $language,
            'request_type' => $requestType,
            'technologies' => $technologies,
            'complexity' => $this->assessCodeComplexity($input),
            'security_risk_level' => $this->assessSecurityRiskLevel($input)
        ];
    }

    protected function detectProgrammingLanguage(string $input): ?string
    {
        foreach ($this->capabilities['supported_languages'] as $language => $frameworks) {
            if (str_contains(strtolower($input), strtolower($language))) {
                return $language;
            }

            foreach ($frameworks as $framework) {
                if (str_contains(strtolower($input), strtolower($framework))) {
                    return $language;
                }
            }
        }

        return null;
    }

    protected function identifyRequestType(string $input): string
    {
        $types = [
            'implementation' => ['create', 'implement', 'build', 'develop'],
            'debug' => ['debug', 'fix', 'issue', 'problem', 'error'],
            'review' => ['review', 'check', 'analyze', 'assess'],
            'optimization' => ['optimize', 'improve', 'performance', 'faster'],
            'refactor' => ['refactor', 'restructure', 'clean', 'improve'],
            'security' => ['security', 'vulnerable', 'protect', 'safe']
        ];

        foreach ($types as $type => $keywords) {
            if (str_contains_any(strtolower($input), $keywords)) {
                return $type;
            }
        }

        return 'general';
    }

    protected function detectTechnologies(string $input): array
    {
        $detected = [];

        foreach ($this->capabilities['supported_languages'] as $language => $frameworks) {
            foreach ($frameworks as $framework) {
                if (str_contains(strtolower($input), strtolower($framework))) {
                    $detected[] = [
                        'type' => 'framework',
                        'name' => $framework,
                        'language' => $language
                    ];
                }
            }
        }

        return $detected;
    }

    protected function getLanguageSpecificTips(string $language = null): array
    {
        if (!$language || !isset($this->capabilities['supported_languages'][$language])) {
            return [];
        }

        return [
            'best_practices' => $this->getLanguageBestPractices($language),
            'common_pitfalls' => $this->getLanguageCommonPitfalls($language),
            'performance_tips' => $this->getLanguagePerformanceTips($language)
        ];
    }

    protected function getSecurityConsiderations(string $context): array
    {
        $considerations = [];

        // Check for security-sensitive operations
        $securityChecks = [
            'user_input' => [
                'patterns' => ['$_GET', '$_POST', 'request', 'input'],
                'recommendations' => [
                    'Always validate and sanitize user input',
                    'Use Laravel\'s validation system',
                    'Implement request validation classes'
                ]
            ],
            'database' => [
                'patterns' => ['DB::', 'database', 'query', 'eloquent'],
                'recommendations' => [
                    'Use prepared statements',
                    'Implement proper SQL injection prevention',
                    'Validate database inputs'
                ]
            ],
            'authentication' => [
                'patterns' => ['auth', 'login', 'password', 'credential'],
                'recommendations' => [
                    'Use Laravel\'s authentication system',
                    'Implement proper password hashing',
                    'Use secure session management'
                ]
            ],
            'file_operations' => [
                'patterns' => ['file', 'upload', 'download', 'storage'],
                'recommendations' => [
                    'Validate file types and sizes',
                    'Implement proper file permissions',
                    'Use secure file storage practices'
                ]
            ],
            'api' => [
                'patterns' => ['api', 'endpoint', 'route', 'http'],
                'recommendations' => [
                    'Implement proper API authentication',
                    'Use rate limiting',
                    'Validate API inputs'
                ]
            ]
        ];

        foreach ($securityChecks as $type => $check) {
            if ($this->containsSecurityPattern($context, $check['patterns'])) {
                $considerations[$type] = [
                    'risk_level' => $this->assessRiskLevel($type, $context),
                    'recommendations' => $check['recommendations'],
                    'code_examples' => $this->getSecurityExamples($type)
                ];
            }
        }

        return $considerations;
    }

    protected function containsSecurityPattern(string $context, array $patterns): bool
    {
        return str_contains_any(strtolower($context), array_map('strtolower', $patterns));
    }

    protected function assessRiskLevel(string $type, string $context): string
    {
        $riskFactors = [
            'user_input' => $this->assessUserInputRisk($context),
            'database' => $this->assessDatabaseRisk($context),
            'authentication' => $this->assessAuthenticationRisk($context),
            'file_operations' => $this->assessFileOperationsRisk($context),
            'api' => $this->assessApiRisk($context)
        ];

        return $riskFactors[$type] ?? 'low';
    }

    protected function assessCodeComplexity(string $input): array
    {
        return [
            'cognitive_complexity' => $this->calculateCognitiveComplexity($input),
            'cyclomatic_complexity' => $this->estimateCyclomaticComplexity($input),
            'dependencies' => $this->analyzeDependencies($input),
            'recommendations' => $this->getComplexityRecommendations($input)
        ];
    }

    protected function calculateCognitiveComplexity(string $input): int
    {
        $complexity = 0;

        // Increment for control structures
        $complexity += substr_count($input, 'if');
        $complexity += substr_count($input, 'for');
        $complexity += substr_count($input, 'while');
        $complexity += substr_count($input, 'switch');

        // Additional complexity for nested structures
        $complexity += $this->countNestedStructures($input);

        return $complexity;
    }

    protected function countNestedStructures(string $input): int
    {
        $count = 0;
        $depth = 0;

        foreach (str_split($input) as $char) {
            if ($char === '{') {
                $depth++;
                if ($depth > 1) {
                    $count++;
                }
            } elseif ($char === '}') {
                $depth--;
            }
        }

        return $count;
    }

    protected function analyzeDependencies(string $input): array
    {
        $dependencies = [
            'imports' => $this->extractImports($input),
            'external_services' => $this->identifyExternalServices($input),
            'database_interactions' => $this->identifyDatabaseInteractions($input)
        ];

        return [
            'count' => count($dependencies['imports']),
            'details' => $dependencies,
            'recommendations' => $this->getDependencyRecommendations($dependencies)
        ];
    }

    protected function extractImports(string $input): array
    {
        $imports = [];
        if (preg_match_all('/use\s+([^;]+);/', $input, $matches)) {
            $imports = $matches[1];
        }
        return array_unique($imports);
    }

    protected function identifyExternalServices(string $input): array
    {
        $services = [];
        $patterns = [
            'http_client' => ['Http::', 'client', 'guzzle'],
            'cache' => ['Cache::', 'redis'],
            'queue' => ['Queue::', 'job'],
            'storage' => ['Storage::', 's3'],
            'email' => ['Mail::', 'notification']
        ];

        foreach ($patterns as $service => $keywords) {
            if (str_contains_any(strtolower($input), $keywords)) {
                $services[] = $service;
            }
        }

        return $services;
    }

    protected function optimizeCode(string $input): array
    {
        $optimizations = [];

        // Performance optimizations
        $performanceIssues = $this->identifyPerformanceIssues($input);
        if (!empty($performanceIssues)) {
            $optimizations['performance'] = [
                'issues' => $performanceIssues,
                'recommendations' => $this->getPerformanceRecommendations($performanceIssues)
            ];
        }

        // Code quality optimizations
        $qualityIssues = $this->identifyCodeQualityIssues($input);
        if (!empty($qualityIssues)) {
            $optimizations['quality'] = [
                'issues' => $qualityIssues,
                'recommendations' => $this->getQualityRecommendations($qualityIssues)
            ];
        }

        // Memory optimizations
        $memoryIssues = $this->identifyMemoryIssues($input);
        if (!empty($memoryIssues)) {
            $optimizations['memory'] = [
                'issues' => $memoryIssues,
                'recommendations' => $this->getMemoryRecommendations($memoryIssues)
            ];
        }

        return $optimizations;
    }

    protected function suggestTestCases(string $input): array
    {
        $testSuggestions = [];

        // Unit tests
        $testSuggestions['unit'] = $this->generateUnitTestSuggestions($input);

        // Feature tests
        $testSuggestions['feature'] = $this->generateFeatureTestSuggestions($input);

        // Integration tests
        $testSuggestions['integration'] = $this->generateIntegrationTestSuggestions($input);

        return $testSuggestions;
    }

    protected function generateUnitTestSuggestions(string $input): array
    {
        $suggestions = [];

        // Analyze methods and properties
        if (preg_match_all('/function\s+(\w+)\s*\((.*?)\)/', $input, $matches)) {
            foreach ($matches[1] as $index => $method) {
                $parameters = $matches[2][$index];
                $suggestions[] = [
                    'method' => $method,
                    'parameters' => $parameters,
                    'test_cases' => $this->generateTestCasesForMethod($method, $parameters),
                    'assertions' => $this->suggestAssertions($method, $input)
                ];
            }
        }

        return $suggestions;
    }

    protected function cacheOptimizedResponse(string $input, array $response): void
    {
        $cacheKey = 'developer_agent_response:' . md5($input);

        Cache::put($cacheKey, [
            'response' => $response,
            'optimizations' => $this->optimizeCode($input),
            'test_suggestions' => $this->suggestTestCases($input),
            'timestamp' => now()
        ], now()->addDay());
    }
}
