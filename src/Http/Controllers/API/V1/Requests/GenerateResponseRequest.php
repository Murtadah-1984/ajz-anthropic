<?php

declare(strict_types=1);

namespace Ajz\Anthropic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


final class GenerateResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000']
        ];
    }
}
