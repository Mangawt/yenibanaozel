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
}
