<?php

namespace Ajz\Anthropic\Http\Controllers\API\V1;

use Ajz\Anthropic\AIAgents\Specialized\DeveloperAgent;
use Ajz\Anthropic\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeveloperAssistanceController extends Controller
{
    public function __construct(
        private readonly DeveloperAgent $developerAgent
    ) {}

    public function generateCode(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'description' => 'required|string|min:10',
                'language' => 'required|string',
                'context' => 'nullable|array'
            ]);

            // Create or continue conversation
            $conversation = Conversation::create([
                'user_id' => auth()->id(),
                'subject' => "Code Generation: {$validated['description']}",
                'status' => 'active',
                'metadata' => [
                    'language' => $validated['language'],
                    'context' => $validated['context'] ?? []
                ]
            ]);

            // Get response from developer agent
            $response = $this->developerAgent->handleRequest(
                conversation: $conversation,
                input: $this->formatCodeRequest($validated)
            );

            return response()->json([
                'code' => $response['content'],
                'analysis' => $response['metadata']['code_analysis'] ?? null,
                'security_considerations' => $response['metadata']['security_considerations'] ?? null,
                'optimization_suggestions' => $response['metadata']['optimizations'] ?? null,
                'test_suggestions' => $response['metadata']['test_suggestions'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Developer Assistant Error', [
                'error' => $e->getMessage(),
                'input' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to generate code',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function formatCodeRequest(array $validated): string
    {
        return <<<EOT
Generate code with the following requirements:

Description:
{$validated['description']}

Programming Language: {$validated['language']}

Additional Context:
{$this->formatContext($validated['context'] ?? [])}

Please provide:
1. Complete implementation
2. Documentation and comments
3. Error handling
4. Type hints and return types
5. Required tests
6. Security considerations
7. Performance considerations
EOT;
    }

    private function formatContext(array $context): string
    {
        return collect($context)
            ->map(fn($value, $key) => "- {$key}: {$value}")
            ->join("\n");
    }
}
