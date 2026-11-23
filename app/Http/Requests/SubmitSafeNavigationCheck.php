<?php

namespace App\Http\Requests;

use App\Services\SafeNavigation\UrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SubmitSafeNavigationCheck extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail('Enter a valid HTTP or HTTPS URL.');

                        return;
                    }

                    try {
                        app(UrlNormalizer::class)->normalize($value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'authorization_confirmed' => ['required', 'accepted'],
            'provider_disclosure_confirmed' => ['required', 'accepted'],
        ];
    }
}
