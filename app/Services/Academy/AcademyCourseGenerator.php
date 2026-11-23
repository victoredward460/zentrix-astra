<?php

namespace App\Services\Academy;

use App\Services\AI\AgentChatProvider;
use App\Exceptions\AgentProviderException;
use Illuminate\Support\Facades\Log;

class AcademyCourseGenerator
{
    public function __construct(private readonly AgentChatProvider $provider) {}

    public function generate(string $topic, string $level, ?string $modelOverride = null): array
    {
        if (! $this->provider->isConfigured()) {
            throw new AgentProviderException(\App\Enums\AgentProviderFailure::NotConfigured);
        }

        $prompt = "Generate a cybersecurity Academy course as strict JSON only. No markdown, no HTML. "
            ."Topic: ".mb_substr(trim($topic), 0, 160).". Level: ".$level.". "
            ."Use responsible defensive practices. Keep every field concise so the full JSON fits in one response. "
            ."Include a course title and summary, exactly 3 lessons, and 2 quiz questions per lesson. "
            ."Limit the course summary to 25 words, each lesson summary to 15 words, each lesson content to 35 words, "
            ."each question prompt and explanation to 15 words, and each option to 6 words. "
            ."Each lesson needs a title, summary, plain-text content, and at least 1 educational video URL you know to be real. "
            ."JSON shape: "
            .'{"title":"...","summary":"...","lessons":[{"title":"...","summary":"...","content":"...","video_urls":["https://..."],'
            .'"quiz":{"title":"...","questions":[{"prompt":"...","options":{"a":"...","b":"...","c":"...","d":"..."},"correct_option":"a","explanation":"..."}]}}]}';

        $reply = $this->provider->complete([
            ['role' => 'system', 'content' => 'You are a cybersecurity education author. Return only valid JSON. Do not fabricate video availability or certifications.'],
            ['role' => 'user', 'content' => $prompt],
        ], maxTokens: 2500, disableReasoning: true, modelOverride: $modelOverride);

        $json = trim($reply->content);
        $json = preg_replace('/\A```(?:json)?\s*/i', '', $json) ?? $json;
        $json = preg_replace('/\s*```\z/', '', $json) ?? $json;
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! $this->validCourse($decoded)) {
            Log::warning('OpenRouter returned unusable Academy JSON.');
            throw new AgentProviderException(\App\Enums\AgentProviderFailure::InvalidResponse);
        }

        return $decoded;
    }

    private function validCourse(array $course): bool
    {
        if (! is_string($course['title'] ?? null) || ! is_string($course['summary'] ?? null)) {
            return false;
        }
        if (! is_array($course['lessons'] ?? null) || count($course['lessons']) < 1) {
            return false;
        }

        foreach ($course['lessons'] as $lesson) {
            if (! is_array($lesson)
                || ! is_string($lesson['title'] ?? null)
                || ! is_string($lesson['summary'] ?? null)
                || ! is_string($lesson['content'] ?? null)
                || ! is_array($lesson['quiz']['questions'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }
}
