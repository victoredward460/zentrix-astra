<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestWebsiteAudit extends FormRequest
{
    public function rules(): array
    {
        return [
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }
}
