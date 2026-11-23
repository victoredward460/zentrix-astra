<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademyLesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademyLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        $lesson = $this->route('lesson');
        $courseId = $this->input('academy_course_id', $lesson instanceof AcademyLesson ? $lesson->academy_course_id : null);

        return [
            'academy_course_id' => ['required', 'integer', 'exists:academy_courses,id'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash:ascii',
                Rule::unique('academy_lessons', 'slug')
                    ->where('academy_course_id', $courseId)
                    ->ignore($lesson instanceof AcademyLesson ? $lesson->id : null),
            ],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:30000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
