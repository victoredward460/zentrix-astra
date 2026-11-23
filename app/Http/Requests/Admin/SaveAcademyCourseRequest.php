<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademyCourse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademyCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'academy_category_id' => ['required', 'integer', 'exists:academy_categories,id'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash:ascii',
                Rule::unique('academy_courses', 'slug')->ignore($course instanceof AcademyCourse ? $course->id : null),
            ],
            'summary' => ['required', 'string', 'max:1500'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:65535'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
