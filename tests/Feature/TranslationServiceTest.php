<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_azure_provider_sends_expected_headers_and_query(): void
    {
        Setting::setValue('translation_provider', 'azure');
        Setting::setValue('translation_fallback', 'fail');
        Setting::setValue('azure_translator_key', 'azure-key');
        Setting::setValue('azure_translator_region', 'westeurope');
        Setting::setValue('azure_translator_endpoint', 'https://api.cognitive.microsofttranslator.com');

        Http::fake([
            'https://api.cognitive.microsofttranslator.com/translate*' => Http::response([
                ['translations' => [['text' => 'Merhaba dünya']]],
            ]),
        ]);

        $translated = app(TranslationService::class)->translateToTurkish('Hello world');

        $this->assertSame('Merhaba dünya', $translated);
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://api.cognitive.microsofttranslator.com/translate')
                && str_contains($request->url(), 'api-version=3.0')
                && str_contains($request->url(), 'to=tr')
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-key')
                && $request->hasHeader('Ocp-Apim-Subscription-Region', 'westeurope')
                && ! str_contains($request->url(), 'azure-key')
                && ! str_contains($request->body(), 'azure-key');
        });
    }

    public function test_azure_empty_text_does_not_send_request(): void
    {
        Setting::setValue('translation_provider', 'azure');
        Http::fake();

        $this->assertNull(app(TranslationService::class)->translateToTurkish(''));
        Http::assertNothingSent();
    }

    public function test_deepl_fx_key_uses_free_endpoint(): void
    {
        Setting::setValue('translation_provider', 'deepl');
        Setting::setValue('translation_fallback', 'fail');
        Setting::setValue('deepl_enabled', '1');
        Setting::setValue('deepl_endpoint_type', 'auto');
        Setting::setValue('deepl_api_key', 'test-key:fx');

        Http::fake([
            'https://api-free.deepl.com/v2/translate' => Http::response([
                'translations' => [['text' => 'Merhaba dunya']],
            ]),
        ]);

        $translated = app(TranslationService::class)->translateToTurkish('Hello world');

        $this->assertSame('Merhaba dunya', $translated);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api-free.deepl.com/v2/translate'
                && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-key:fx')
                && ! str_contains($request->body(), 'auth_key')
                && str_contains($request->body(), 'target_lang=TR');
        });
    }

    public function test_deepl_usage_uses_authorization_header(): void
    {
        Setting::setValue('deepl_enabled', '1');
        Setting::setValue('deepl_endpoint_type', 'auto');
        Setting::setValue('deepl_api_key', 'test-key:fx');

        Http::fake([
            'https://api-free.deepl.com/v2/translate' => Http::response([
                'translations' => [['text' => 'Test cevirisi']],
            ]),
            'https://api-free.deepl.com/v2/usage' => Http::response([
                'character_count' => 42,
                'character_limit' => 500000,
            ]),
        ]);

        $result = app(TranslationService::class)->testProvider('deepl');

        $this->assertTrue($result['ok']);
        $this->assertSame(42, $result['usage']['character_count']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api-free.deepl.com/v2/usage'
                && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-key:fx')
                && ! str_contains($request->url(), 'auth_key');
        });
    }

    public function test_deepl_non_fx_key_uses_pro_endpoint(): void
    {
        Setting::setValue('translation_provider', 'deepl');
        Setting::setValue('translation_fallback', 'fail');
        Setting::setValue('deepl_enabled', '1');
        Setting::setValue('deepl_endpoint_type', 'auto');
        Setting::setValue('deepl_api_key', 'pro-key');

        Http::fake([
            'https://api.deepl.com/v2/translate' => Http::response([
                'translations' => [['text' => 'Merhaba']],
            ]),
        ]);

        $this->assertSame('Merhaba', app(TranslationService::class)->translateToTurkish('Hello'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.deepl.com/v2/translate'
            && $request->hasHeader('Authorization', 'DeepL-Auth-Key pro-key')
            && ! str_contains($request->url(), 'auth_key')
            && ! str_contains($request->body(), 'auth_key'));
    }

    public function test_translation_service_has_no_legacy_deepl_auth_key_usage(): void
    {
        $source = file_get_contents(app_path('Services/TranslationService.php'));

        $this->assertStringNotContainsString("'auth_key'", $source);
        $this->assertStringNotContainsString('"auth_key"', $source);
    }

    public function test_fallback_is_not_silent_when_fail_is_configured(): void
    {
        Setting::setValue('translation_provider', 'deepl');
        Setting::setValue('translation_fallback', 'fail');
        Setting::setValue('deepl_enabled', '0');
        Setting::setValue('deepl_api_key', 'test-key:fx');

        $this->expectException(\RuntimeException::class);
        app(TranslationService::class)->translateToTurkish('Original text');
    }

    public function test_disabled_deepl_keeps_original_when_fallback_is_original(): void
    {
        Setting::setValue('translation_provider', 'deepl');
        Setting::setValue('translation_fallback', 'original');
        Setting::setValue('deepl_enabled', '0');
        Setting::setValue('deepl_api_key', 'test-key:fx');

        Http::fake();

        $translated = app(TranslationService::class)->translateToTurkish('Original text');

        $this->assertSame('Original text', $translated);
        Http::assertNothingSent();
    }

    public function test_translation_chain_moves_from_gemini_quota_error_to_google(): void
    {
        Setting::setValue('translation_provider', 'gemini');
        Setting::setValue('translation_fallback', 'original');
        Setting::setValue('translation_provider_chain', 'gemini,google,azure');
        Setting::setValue('gemini_enabled', '1');
        Setting::setValue('gemini_api_key', 'gemini-key');
        Setting::setValue('google_translate_enabled', '1');
        Setting::setValue('google_translate_api_key', 'google-key');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
                'error' => ['message' => 'quota exceeded'],
            ], 429),
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'data' => ['translations' => [['translatedText' => 'Google çevirisi']]],
            ]),
        ]);

        $translated = app(TranslationService::class)->translateToTurkish('English summary');

        $this->assertSame('Google çevirisi', $translated);
        Http::assertSent(function ($request): bool {
            if (! str_starts_with($request->url(), 'https://generativelanguage.googleapis.com')) {
                return false;
            }

            $payload = json_decode($request->body(), true);

            return str_contains($payload['system_instruction']['parts'][0]['text'] ?? '', 'cultivation → yetişim')
                && ($payload['generationConfig']['temperature'] ?? null) === 0.1
                && ($payload['generationConfig']['maxOutputTokens'] ?? null) === 2048;
        });
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://translation.googleapis.com/language/translate/v2'));
    }

    public function test_html_summary_is_not_stripped_before_translation(): void
    {
        Setting::setValue('translation_provider', 'google');
        Setting::setValue('translation_fallback', 'fail');
        Setting::setValue('google_translate_enabled', '1');
        Setting::setValue('google_translate_api_key', 'google-key');

        Http::fake([
            'https://translation.googleapis.com/language/translate/v2*' => Http::response([
                'data' => ['translations' => [['translatedText' => '<p>Merhaba <strong>dünya</strong>.</p>']]],
            ]),
        ]);

        $translated = app(TranslationService::class)->translateToTurkish('<p>Hello <strong>world</strong>.</p>');

        $this->assertSame('<p>Merhaba <strong>dünya</strong>.</p>', $translated);
        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return ($body['q'] ?? null) === '<p>Hello <strong>world</strong>.</p>'
                && ($body['format'] ?? null) === 'html';
        });
    }
}
