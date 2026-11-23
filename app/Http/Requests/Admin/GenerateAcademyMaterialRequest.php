<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAcademyMaterialRequest extends FormRequest
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
            'topic' => ['required', 'string', 'min:3', 'max:160'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
        ];
    }
}
