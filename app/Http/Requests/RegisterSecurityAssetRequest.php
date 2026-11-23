<?php

namespace App\Http\Requests;

use App\Services\Scanner\Exceptions\InvalidHostname;
use App\Services\Scanner\HostnamePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSecurityAssetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $hostname = $this->input('hostname');

        if (is_string($hostname)) {
            $this->merge(['hostname' => strtolower(rtrim(trim($hostname), '.'))]);
        }
    }

    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:253',
                Rule::unique('security_assets', 'hostname')
                    ->where('user_id', $this->user()?->getAuthIdentifier()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail('Enter a public DNS hostname.');

                        return;
                    }

                    try {
                        app(HostnamePolicy::class)->normalize($value);
                    } catch (InvalidHostname) {
                        $fail('Enter a fully qualified public DNS hostname. URLs, IP addresses, and reserved names are not accepted.');
                    }
                },
            ],
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }
}
