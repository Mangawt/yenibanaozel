<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranslationService
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function translateToTurkish(?string $text): ?string
    {
        $text = trim(strip_tags((string) $text));

        if ($text === '') {
            return null;
        }

        $provider = $this->settings->get('translation_provider', 'deepl');

        $translated = match ($provider) {
            'deepl' => $this->translateWithDeepL($text),
            'google' => $this->translateWithGoogle($text),
            'gemini' => $this->translateWithGemini($text),
            'none' => $text,
            default => $this->translateWithPublicGoogle($text),
        };

        Log::channel('import')->info('Çeviri tamamlandı.', [
            'provider' => $provider,
            'length' => mb_strlen($text),
        ]);

        return $translated;
    }

    private function translateWithDeepL(string $text): string
    {
        $key = $this->settings->get('deepl_api_key');

        if ($this->settings->get('deepl_enabled', '0') !== '1' || blank($key)) {
            return $this->translateWithPublicGoogle($text);
        }

        $endpoint = Str::contains($key, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $response = $this->http()->asForm()->timeout(20)->post($endpoint, [
            'auth_key' => $key,
            'text' => $text,
            'target_lang' => 'TR',
        ]);

        return $response->json('translations.0.text') ?: $this->translateWithPublicGoogle($text);
    }

    private function translateWithGoogle(string $text): string
    {
        $key = $this->settings->get('google_translate_api_key');

        if ($this->settings->get('google_translate_enabled', '0') !== '1' || blank($key)) {
            return $this->translateWithPublicGoogle($text);
        }

        $response = $this->http()->timeout(20)->post("https://translation.googleapis.com/language/translate/v2?key={$key}", [
            'q' => $text,
            'target' => 'tr',
            'format' => 'text',
        ]);

        return $response->json('data.translations.0.translatedText') ?: $this->translateWithPublicGoogle($text);
    }

    private function translateWithGemini(string $text): string
    {
        $key = $this->settings->get('gemini_api_key');

        if ($this->settings->get('gemini_enabled', '0') !== '1' || blank($key)) {
            return $this->translateWithPublicGoogle($text);
        }

        $response = $this->http()->timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$key}",
            [
                'contents' => [[
                    'parts' => [[
                        'text' => "Aşağıdaki anime/manga özetini doğal Türkçeye çevir. Sadece çeviriyi yaz:\n\n{$text}",
                    ]],
                ]],
            ],
        );

        return $response->json('candidates.0.content.parts.0.text') ?: $this->translateWithPublicGoogle($text);
    }

    private function translateWithPublicGoogle(string $text): string
    {
        $response = $this->http()->timeout(20)->get('https://translate.googleapis.com/translate_a/single', [
            'client' => 'gtx',
            'sl' => 'auto',
            'tl' => 'tr',
            'dt' => 't',
            'q' => $text,
        ]);

        $chunks = collect($response->json('0', []))
            ->pluck('0')
            ->filter()
            ->implode('');

        return $chunks !== '' ? html_entity_decode($chunks, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $text;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'verify' => (bool) config('services.http.verify_ssl', true),
        ]);
    }
}
