<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function generateMotivationalQuote(int $userId): array
    {
        $cacheKey = "gemini_quote_user_{$userId}_" . today()->toDateString();
        
        return Cache::remember($cacheKey, 86400, function () {
            if (!$this->apiKey) return ['text' => 'Stay hard!', 'author' => 'David Goggins']; // Fallback if no key

            $response = $this->callGemini(
                "Generate a motivational quote for a personal productivity app user. " .
                "Max 20 words, unique and inspiring. " .
                "Return ONLY valid JSON with fields: text (string) and author (string)."
            );

            return $response ?? ['text' => 'Focus moves mountains.', 'author' => 'Ancient Proverb'];
        });
    }

    public function analyzeJournalMood(string $content): array
    {
        if (!$this->apiKey) return ['mood' => 'neutral', 'summary' => 'AI is disabled.'];

        $response = $this->callGemini(
            "Analyze this journal entry and determine the overall sentiment. Entry: \"{$content}\". " .
            "Return ONLY valid JSON: {\"mood\": \"great|good|neutral|bad|awful\", \"summary\": \"one sentence\"}"
        );

        return $response ?? ['mood' => 'neutral', 'summary' => 'Unable to analyze at this time.'];
    }

    private function callGemini(string $prompt): mixed
    {
        try {
            $response = Http::timeout(10)->post(
                "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 256,
                    ]
                ]
            );

            if (!$response->successful()) return null;

            $text = $response->json('candidates.0.content.parts.0.text');
            if (!$text) return null;

            // Strip any markdown blocks Google might add like ```json ... ```
            $text = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', trim($text));
            return json_decode(trim($text), true);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gemini API error: ' . $e->getMessage());
            return null;
        }
    }
}