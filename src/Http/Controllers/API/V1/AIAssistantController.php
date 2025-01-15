<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Controllers\API\V1;

use Ajz\Anthropic\Http\Controllers\Controller;
use Ajz\Anthropic\Http\Resources\AIAssistantResource;
use Ajz\Anthropic\Http\Requests\CreateAIAssistantRequest;
use Ajz\Anthropic\Http\Requests\GenerateResponseRequest;
use Ajz\Anthropic\Factories\AIAssistantFactory;
use Ajz\Anthropic\DTOs\AssistantConfig;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 *     name="AI Assistants",
 *     description="API Endpoints for AI Assistant management"
 * )
 */
class AIAssistantController extends Controller
{
    public function __construct(
        private readonly AIAssistantFactory $assistantFactory
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/assistants",
     *     summary="Create a new AI Assistant",
     *     tags={"AI Assistants"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateAIAssistantRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="AI Assistant created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/AIAssistantResource")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function store(CreateAIAssistantRequest $request): JsonResponse
    {
        try {
            $config = new AssistantConfig(
                role: $request->validated('role'),
                name: $request->validated('name'),
                documentationUrls: $request->validated('documentation_urls'),
                bestPractices: $request->validated('best_practices'),
                knowledgeBase: $request->validated('knowledge_base')
            );

            $assistant = $this->assistantFactory->create($config);

            return new JsonResponse(
                new AIAssistantResource($assistant),
                201
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/assistants/{assistant}/generate",
     *     summary="Generate a response using an AI Assistant",
     *     tags={"AI Assistants"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="assistant",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/GenerateResponseRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Response generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="response", type="string")
     *         )
     *     )
     * )
     */
    public function generate(
        string $assistantId,
        GenerateResponseRequest $request
    ): JsonResponse {
        try {
            $assistant = $this->assistantFactory->getById($assistantId);
            $response = $assistant->generateResponse($request->prompt);

            return new JsonResponse(['response' => $response]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(\Exception $e): JsonResponse
    {
        logger()->error('AI Assistant Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return new JsonResponse([
            'error' => 'An error occurred while processing your request',
            'message' => $e->getMessage()
        ], 500);
    }
}
