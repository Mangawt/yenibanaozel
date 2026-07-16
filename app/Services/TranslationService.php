<?php

namespace App\Services;

use App\Services\Translation\AzureTranslator;
use Illuminate\Http\Client\PendingRequest;
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
        $text = trim((string) $text);
        $hash = hash('sha256', $text);

        if ($text === '') {
            Log::channel('translation')->info('Translation skipped.', [
                'reason' => 'empty_text',
                'target_lang' => 'TR',
            ]);

            return null;
        }

        $primaryProvider = (string) $this->settings->get('translation_provider', config('services.translation.provider', 'azure'));

        Log::channel('translation')->info('Translation started.', [
            'provider' => $primaryProvider,
            'chain' => $this->providerChain($primaryProvider),
            'target_lang' => 'TR',
            'length' => mb_strlen($text),
            'text_hash' => $hash,
        ]);

        $failures = [];

        foreach ($this->providerChain($primaryProvider) as $provider) {
            try {
                $translated = $this->translateWithProvider($provider, $text);

                Log::channel('translation')->info('Translation completed.', [
                    'provider' => $provider,
                    'primary_provider' => $primaryProvider,
                    'target_lang' => 'TR',
                    'length' => mb_strlen($text),
                    'translated_length' => mb_strlen($translated),
                    'text_hash' => $hash,
                    'fallback_used' => $provider !== $primaryProvider,
                ]);

                return $translated;
            } catch (\Throwable $exception) {
                $failures[$provider] = $exception->getMessage();

                Log::channel('translation')->warning('Translation provider failed, trying next provider.', [
                    'provider' => $provider,
                    'primary_provider' => $primaryProvider,
                    'target_lang' => 'TR',
                    'length' => mb_strlen($text),
                    'text_hash' => $hash,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::channel('translation')->error('Translation chain failed.', [
            'primary_provider' => $primaryProvider,
            'target_lang' => 'TR',
            'length' => mb_strlen($text),
            'text_hash' => $hash,
            'failures' => $failures,
        ]);

        if ($this->settings->get('translation_fallback', 'original') === 'fail') {
            throw new \RuntimeException('Tüm çeviri sağlayıcıları başarısız oldu: '.json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $this->fallback($text, $primaryProvider, json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function testProvider(string $provider): array
    {
        $text = 'This is a translation test.';

        try {
            $translated = $this->translateWithProvider($provider, $text);

            return [
                'ok' => true,
                'provider' => $provider,
                'message' => 'Bağlantı başarılı.',
                'translated' => $translated,
                'usage' => $provider === 'deepl' ? $this->deepLUsage() : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $provider,
                'message' => $exception->getMessage(),
                'translated' => null,
                'usage' => null,
            ];
        }
    }

    private function translateWithProvider(string $provider, string $text): string
    {
        return match ($provider) {
            'azure' => (string) app(AzureTranslator::class)->translate($text, 'tr'),
            'deepl' => $this->translateWithDeepL($text),
            'google' => $this->translateWithGoogle($text),
            'gemini' => $this->translateWithGemini($text),
            'none' => $text,
            default => throw new \InvalidArgumentException('Desteklenmeyen çeviri sağlayıcısı: '.$provider),
        };
    }

    private function providerChain(string $primaryProvider): array
    {
        if ($primaryProvider === 'none') {
            return ['none'];
        }

        $configured = (string) $this->settings->get('translation_provider_chain', 'gemini,google,azure');
        $chain = collect(explode(',', $configured))
            ->map(fn (string $provider): string => trim($provider))
            ->filter(fn (string $provider): bool => in_array($provider, ['gemini', 'google', 'azure', 'deepl'], true))
            ->values()
            ->all();

        return collect([$primaryProvider, ...$chain])
            ->filter(fn (string $provider): bool => $provider !== 'none')
            ->unique()
            ->values()
            ->all();
    }

    private function translateWithDeepL(string $text): string
    {
        $key = trim((string) $this->settings->get('deepl_api_key'));

        if ($this->settings->get('deepl_enabled', '0') !== '1') {
            throw new \RuntimeException('DeepL aktif değil.');
        }

        if ($key === '') {
            throw new \RuntimeException('DeepL API anahtarı boş.');
        }

        $endpoint = $this->deepLEndpoint($key).'/v2/translate';
        $startedAt = microtime(true);
        $response = $this->deepLHttp($key)
            ->asForm()
            ->timeout(25)
            ->post($endpoint, [
                'text' => $text,
                'target_lang' => 'TR',
                'tag_handling' => Str::contains($text, ['<p', '<br', '<strong', '<em', '<i', '<b']) ? 'html' : null,
            ]);

        Log::channel('translation')->info('DeepL request completed.', [
            'endpoint' => $this->maskedEndpoint($endpoint),
            'http_status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'length' => mb_strlen($text),
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('DeepL HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 300));
        }

        $translated = $response->json('translations.0.text');

        if (blank($translated)) {
            throw new \RuntimeException('DeepL boş çeviri döndü.');
        }

        return html_entity_decode((string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function translateWithGoogle(string $text): string
    {
        $key = trim((string) $this->settings->get('google_translate_api_key'));

        if ($this->settings->get('google_translate_enabled', '0') !== '1' || $key === '') {
            throw new \RuntimeException('Google Translate aktif değil veya anahtar boş.');
        }

        $response = $this->http()->timeout(20)->post("https://translation.googleapis.com/language/translate/v2?key={$key}", [
            'q' => $text,
            'target' => 'tr',
            'format' => Str::contains($text, ['<p', '<br', '<strong', '<em', '<i', '<b']) ? 'html' : 'text',
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Google Translate HTTP '.$response->status());
        }

        $translated = $response->json('data.translations.0.translatedText');

        if (blank($translated)) {
            throw new \RuntimeException('Google Translate boş çeviri döndü.');
        }

        return html_entity_decode((string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function translateWithGemini(string $text): string
    {
        $key = trim((string) $this->settings->get('gemini_api_key'));

        if ($this->settings->get('gemini_enabled', '0') !== '1' || $key === '') {
            throw new \RuntimeException('Gemini aktif değil veya anahtar boş.');
        }

        $systemInstruction = <<<'TEXT'
Sen anime, manga, manhwa ve light novel özetlerini İngilizceden Türkçeye çeviren profesyonel bir çevirmensin.

Zorunlu kurallar:
- Kaynak metindeki bütün bilgileri koru.
- Bilgi ekleme, çıkarma, yorumlama veya özetleme.
- Metni gereksiz yere uzatma.
- Karakter ve eser adlarını çevirmeden koru.
- Japonca hitap eklerini kaynakta yoksa ekleme.
- Cinsiyeti belirsiz karakterlere cinsiyet atama.
- “Cultivation”, “sect”, “dungeon”, “regressor”, “awakened” gibi terimleri bağlama uygun Türkçeye çevir.
- Yerleşmiş Türkçe karşılığı olmayan özel güç ve teknik isimlerini koru.
- İngilizce cümle yapısını birebir kopyalama; doğal Türkçe kullan.
- HTML etiketlerini ve paragraf yapısını koru.
- Kaynak metindeki parantezleri ve içerik uyarılarını koru.
- Başlık, açıklama, çevirmen notu veya “Çeviri:” ifadesi ekleme.
- Markdown kod bloğu kullanma.
- Yalnızca son Türkçe çeviriyi döndür.

Terim sözlüğü:
- cultivation → yetişim
- cultivator → yetişimci
- sect → tarikat
- martial arts → dövüş sanatları
- spiritual energy → ruhsal enerji
- awakened → uyanmış
- regressor → geçmişe dönen
- hunter → avcı
- dungeon → zindan
- demon lord → iblis kral

Terim sözlüğünü bağlama uygun olduğu durumlarda uygula.
Özel isim olarak kullanılan terimleri çevirme.
TEXT;

        $response = $this->http()->timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$key}",
            [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "Aşağıdaki özeti Türkçeye çevir:\n\n".$text],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 4096,
                ],
            ],
        );

        if (! $response->ok()) {
            throw new \RuntimeException('Gemini HTTP '.$response->status());
        }

        $translated = $response->json('candidates.0.content.parts.0.text');

        if (blank($translated)) {
            throw new \RuntimeException('Gemini boş çeviri döndü.');
        }

        return $this->cleanGeminiOutput((string) $translated);
    }

    private function fallback(string $text, string $failedProvider, string $reason): string
    {
        $fallback = (string) $this->settings->get('translation_fallback', 'original');

        Log::channel('translation')->warning('Translation fallback used.', [
            'failed_provider' => $failedProvider,
            'fallback' => $fallback,
            'reason' => $reason,
            'length' => mb_strlen($text),
            'text_hash' => hash('sha256', $text),
        ]);

        return match ($fallback) {
            'google' => $this->translateWithGoogle($text),
            'gemini' => $this->translateWithGemini($text),
            'public_google' => $this->translateWithPublicGoogle($text),
            default => $text,
        };
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

    private function cleanGeminiOutput(string $translated): string
    {
        $translated = trim($translated);

        if (Str::startsWith($translated, '```')) {
            $translated = preg_replace('/^```[a-zA-Z]*\s*/', '', $translated) ?? $translated;
            $translated = preg_replace('/\s*```$/', '', $translated) ?? $translated;
        }

        return trim($translated);
    }

    private function deepLEndpoint(string $key): string
    {
        $mode = (string) $this->settings->get('deepl_endpoint_type', 'auto');

        return match ($mode) {
            'free' => 'https://api-free.deepl.com',
            'pro' => 'https://api.deepl.com',
            default => Str::contains($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com',
        };
    }

    private function deepLUsage(): ?array
    {
        $key = trim((string) $this->settings->get('deepl_api_key'));

        if ($key === '') {
            return null;
        }

        $endpoint = $this->deepLEndpoint($key).'/v2/usage';
        $response = $this->deepLHttp($key)
            ->timeout(15)
            ->get($endpoint);

        if (! $response->ok()) {
            return [
                'ok' => false,
                'http_status' => $response->status(),
                'checked_at' => now()->toDateTimeString(),
            ];
        }

        return [
            'ok' => true,
            'character_count' => $response->json('character_count'),
            'character_limit' => $response->json('character_limit'),
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    private function maskedEndpoint(string $endpoint): string
    {
        return str_replace(['https://'], '', $endpoint);
    }

    private function deepLHttp(string $key): PendingRequest
    {
        return $this->http()->withHeaders([
            'Authorization' => 'DeepL-Auth-Key '.$key,
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'verify' => (bool) config('services.http.verify_ssl', true),
        ]);
    }
}
