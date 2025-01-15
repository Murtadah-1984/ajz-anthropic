<?php

declare(Strict_types=1);

namespace Ajz\Anthropic\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class AIAssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'name' => $this->name,
            'documentation_urls' => $this->documentationUrls,
            'best_practices' => $this->bestPractices,
            'knowledge_base' => $this->knowledgeBase,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String()
        ];
    }
}
