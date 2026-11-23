<?php

namespace App\Http\Requests;

use App\Services\FileCheck\FileCheckService;
use Illuminate\Foundation\Http\FormRequest;

class UploadFileCheck extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.FileCheckService::MAX_SIZE_KILOBYTES],
        ];
    }
}
