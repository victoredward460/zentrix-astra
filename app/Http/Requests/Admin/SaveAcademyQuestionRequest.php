<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademyQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:1000'],
            'options.a' => ['required', 'string', 'max:300'],
            'options.b' => ['required', 'string', 'max:300'],
            'options.c' => ['required', 'string', 'max:300'],
            'options.d' => ['required', 'string', 'max:300'],
            'correct_option' => ['required', Rule::in(['a', 'b', 'c', 'd'])],
            'explanation' => ['required', 'string', 'max:1500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
