<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveAcademyQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'pass_percentage' => ['required', 'integer', 'min:50', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
