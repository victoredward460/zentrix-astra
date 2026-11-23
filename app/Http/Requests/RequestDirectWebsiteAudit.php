<?php

namespace App\Http\Requests;

use App\Services\SafeNavigation\UrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class RequestDirectWebsiteAudit extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $url = $this->input('url');
                if (! is_string($url)) {
                    return;
                }

                try {
                    app(UrlNormalizer::class)->normalize($url);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('url', $exception->getMessage());
                }
            },
        ];
    }
}
