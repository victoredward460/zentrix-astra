<?php

namespace App\Services\Scanner;

use App\Enums\AssetVerificationStatus;
use App\Models\SecurityAsset;
use App\Services\Scanner\Exceptions\OwnershipVerificationFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DomainOwnershipService
{
    public function __construct(private readonly DnsResolver $dnsResolver) {}

    public function issue(SecurityAsset $asset): OwnershipChallenge
    {
        if ($asset->verification_status === AssetVerificationStatus::Verified) {
            throw new OwnershipVerificationFailed('Verified assets do not need a new challenge.');
        }

        $token = Str::random(48);

        $asset->forceFill([
            'verification_status' => AssetVerificationStatus::Pending,
            'verification_token_hash' => hash('sha256', $token),
            'verification_expires_at' => now()->addHours(24),
            'verified_at' => null,
        ])->save();

        return new OwnershipChallenge($asset, $token);
    }

    public function verify(SecurityAsset $asset): void
    {
        if (
            $asset->verification_status !== AssetVerificationStatus::Pending
            || ! is_string($asset->verification_token_hash)
            || $asset->verification_expires_at === null
            || $asset->verification_expires_at->isPast()
        ) {
            throw new OwnershipVerificationFailed('Generate a fresh ownership challenge before verifying.');
        }

        $recordName = '_zentrix-astra-verification.'.$asset->hostname;
        $records = $this->dnsResolver->records($recordName, DNS_TXT);
        $expectedHash = $asset->verification_token_hash;
        $found = false;

        foreach ($records as $record) {
            foreach ($this->txtValues($record) as $value) {
                $prefix = 'zentrix-astra-verification=';

                if (
                    str_starts_with($value, $prefix)
                    && hash_equals($expectedHash, hash('sha256', substr($value, strlen($prefix))))
                ) {
                    $found = true;

                    break 2;
                }
            }
        }

        if (! $found) {
            throw new OwnershipVerificationFailed('The DNS ownership record was not found. Check the record and try again.');
        }

        $updated = DB::table('security_assets')
            ->where('id', $asset->getKey())
            ->where('verification_status', AssetVerificationStatus::Pending->value)
            ->where('verification_token_hash', $expectedHash)
            ->where('verification_expires_at', '>=', now())
            ->update([
                'verification_status' => AssetVerificationStatus::Verified->value,
                'verification_token_hash' => null,
                'verification_expires_at' => null,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new OwnershipVerificationFailed('The ownership challenge changed or expired. Generate a fresh challenge.');
        }

        $asset->refresh();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    private function txtValues(array $record): array
    {
        $values = [];

        if (is_string($record['txt'] ?? null)) {
            $values[] = $record['txt'];
        }

        if (is_array($record['entries'] ?? null)) {
            foreach ($record['entries'] as $entry) {
                if (is_string($entry)) {
                    $values[] = $entry;
                }
            }
        }

        return $values;
    }
}
