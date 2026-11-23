<?php

namespace App\Services\FileCheck;

class MessageAnalysisService
{
    /**
     * @return array{level: string, summary: string, signals: list<string>, url_count: int}
     */
    public function analyze(string $message): array
    {
        $signals = [];
        $urls = $this->urlsIn($message);
        $credentialRequest = preg_match(
            '/\b(password|passcode|one[- ]time code|verification code|recovery code|mfa code)\b.{0,60}\b(send|share|reply|enter|provide|confirm|submit)\b|\b(send|share|reply|enter|provide|confirm|submit)\b.{0,60}\b(password|passcode|one[- ]time code|verification code|recovery code|mfa code)\b/i',
            $message,
        ) === 1;
        $paymentRequest = preg_match(
            '/\b(gift cards?|wire transfer|bank transfer|cryptocurrency|bitcoin|payment|invoice|refund|wallet)\b/i',
            $message,
        ) === 1 && preg_match(
            '/\b(send|pay|buy|purchase|transfer|deposit|wire|refund|reimburse)\b/i',
            $message,
        ) === 1;
        $urgency = preg_match(
            '/\b(act now|immediately|urgent|final warning|within \d+\s*(minutes?|hours?|days?)|account.{0,30}(suspended|closed|locked))\b/i',
            $message,
        ) === 1;
        $accountPressure = preg_match(
            '/\b(account|mailbox|password|security team|support team|it department|bank account)\b/i',
            $message,
        ) === 1 && preg_match(
            '/\b(verify|confirm|unlock|reset|suspend|update|sign in|log in|click|provide|enter)\b/i',
            $message,
        ) === 1;

        if ($credentialRequest) {
            $signals[] = 'The message asks the reader to share or enter an authentication secret.';
        }

        if ($paymentRequest) {
            $signals[] = 'The message asks the reader to make a payment or transfer funds.';
        }

        if ($urgency) {
            $signals[] = 'The message uses urgent or threatening language.';
        }

        if ($accountPressure && ! $credentialRequest) {
            $signals[] = 'The message uses account or support language with a request to act.';
        }

        foreach ($urls as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $signals[] = 'A link uses unencrypted HTTP.';
            }

            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $signals[] = 'A link points directly to an IP address.';
            }

            if (str_contains($host, 'xn--')) {
                $signals[] = 'A link contains an internationalized (punycode) hostname that should be checked carefully.';
            }

            if (in_array($host, ['bit.ly', 'tinyurl.com', 't.co', 'is.gd', 'cutt.ly', 'rebrand.ly'], true)) {
                $signals[] = 'A link uses a URL-shortening service that hides its final destination.';
            }
        }

        $signals = array_values(array_unique($signals));
        $level = $credentialRequest || ($paymentRequest && $urgency)
            ? 'High concern'
            : ($signals === [] ? 'No obvious warning found' : 'Review recommended');

        return [
            'level' => $level,
            'summary' => $signals === []
                ? 'No obvious warning was detected by these local heuristics. This is not proof that the message or any link is safe.'
                : 'One or more common warning signs were detected. Verify the sender and request through a separate trusted channel before taking action.',
            'signals' => $signals,
            'url_count' => count($urls),
        ];
    }

    /**
     * @return list<string>
     */
    private function urlsIn(string $message): array
    {
        preg_match_all('~https?://[^\s<>"\'()]+~iu', $message, $matches);

        $urls = array_map(
            static fn (string $url): string => rtrim($url, '.,!?;:'),
            $matches[0] ?? [],
        );

        return array_values(array_unique(array_filter(
            $urls,
            static fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_HOST) !== null,
        )));
    }
}
