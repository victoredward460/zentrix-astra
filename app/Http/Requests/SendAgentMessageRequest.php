<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAgentMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.config('ai.message.max_input_chars')],
            'security_finding_id' => ['nullable', 'integer', 'min:1'],
            'model' => ['sometimes', 'required', 'string', Rule::in(array_keys((array) config('ai.openrouter.models', [])))],
        ];
    }
}
