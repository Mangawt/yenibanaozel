<?php

namespace App\Services\Translation;

use App\Contracts\TranslatorInterface;
use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AzureTranslator implements TranslatorInterface
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function translate(?string $text, string $targetLanguage = 'tr'): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $chunks = $this->chunks($text);
        $translated = [];

        foreach ($chunks as $chunk) {
            $translated[] = $this->translateChunk($chunk, $targetLanguage);
        }

        return trim(implode("\n\n", $translated));
    }

    private function translateChunk(string $text, string $targetLanguage): string
    {
        $key = trim((string) $this->settings->get('azure_translator_key', config('services.azure_translator.key')));
        $region = trim((string) $this->settings->get('azure_translator_region', config('services.azure_translator.region')));
        $endpoint = rtrim((string) $this->settings->get('azure_translator_endpoint', config('services.azure_translator.endpoint')), '/');
        $version = (string) $this->settings->get('azure_translator_api_version', config('services.azure_translator.api_version', '3.0'));
        $timeout = (int) $this->settings->get('azure_translator_timeout', config('services.azure_translator.timeout', 30));

        if ($key === '' || $region === '') {
            throw new \RuntimeException('Azure Translator anahtarı veya region boş.');
        }

        $hash = hash('sha256', $text);
        $response = Http::withOptions(['verify' => (bool) config('services.http.verify_ssl', true)])
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
                'Ocp-Apim-Subscription-Region' => $region,
                'Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'application/json',
            ])
            ->timeout($timeout)
            ->post($endpoint.'/translate?'.http_build_query([
                'api-version' => $version,
                'to' => $targetLanguage,
                'textType' => Str::contains($text, ['<p', '<br', '<strong', '<em']) ? 'html' : 'plain',
            ]), [['Text' => $text]]);

        Log::channel('translation')->info('Azure Translator request completed.', [
            'http_status' => $response->status(),
            'length' => mb_strlen($text),
            'text_hash' => $hash,
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            $this->recordFailure('auth_error');
            throw new \RuntimeException('Azure Translator yetkilendirme hatası. Anahtar, region veya endpoint ayarını kontrol et.');
        }

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            Setting::setValue('azure_translator_last_429_at', now()->toDateTimeString());
            $this->recordFailure('rate_limited');
            Log::channel('translation')->warning('Azure Translator rate limited.', [
                'retry_after' => max(60, $retryAfter),
                'text_hash' => $hash,
            ]);
            throw new \RuntimeException('Azure Translator 429. Retry-After: '.max(60, $retryAfter));
        }

        if ($response->serverError()) {
            $this->recordFailure('server_error_'.$response->status());
            throw new \RuntimeException('Azure Translator geçici sunucu hatası: '.$response->status());
        }

        if (! $response->ok()) {
            $this->recordFailure('http_'.$response->status());
            throw new \RuntimeException('Azure Translator HTTP '.$response->status());
        }

        $translated = $response->json('0.translations.0.text');

        if (blank($translated)) {
            $this->recordFailure('empty_translation');
            throw new \RuntimeException('Azure Translator boş çeviri döndü.');
        }

        $this->recordSuccess(mb_strlen($text));

        return html_entity_decode((string) $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function recordSuccess(int $characters): void
    {
        Setting::setValue('azure_translator_success_count', (string) (((int) Setting::getValue('azure_translator_success_count', 0)) + 1));
        Setting::setValue('azure_translator_character_count', (string) (((int) Setting::getValue('azure_translator_character_count', 0)) + $characters));
        Setting::setValue('azure_translator_last_success_at', now()->toDateTimeString());
    }

    private function recordFailure(string $reason): void
    {
        Setting::setValue('azure_translator_failed_count', (string) (((int) Setting::getValue('azure_translator_failed_count', 0)) + 1));
        Setting::setValue('azure_translator_last_error', $reason);
    }

    private function chunks(string $text): array
    {
        if (mb_strlen($text) <= 4500) {
            return [$text];
        }

        $parts = preg_split('/(\R{2,}|(?<=[.!?])\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $chunks = [];
        $buffer = '';

        foreach ($parts as $part) {
            if (mb_strlen($buffer.$part) > 4500 && trim($buffer) !== '') {
                $chunks[] = trim($buffer);
                $buffer = '';
            }
            $buffer .= $part;
        }

        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        return $chunks;
    }
}
