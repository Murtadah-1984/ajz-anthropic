<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Ajz\Anthropic\Enums\AssistantRole;

final class CreateAIAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Assuming authentication is handled via middleware
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:' . implode(',', array_column(AssistantRole::cases(), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'documentation_urls' => ['required', 'array'],
            'documentation_urls.*' => ['url'],
            'best_practices' => ['required', 'array'],
            'best_practices.*.category' => ['required', 'string'],
            'best_practices.*.practices' => ['required', 'array'],
            'knowledge_base' => ['required', 'array'],
            'knowledge_base.*.topic' => ['required', 'string'],
            'knowledge_base.*.content' => ['required', 'string']
        ];
    }
}
