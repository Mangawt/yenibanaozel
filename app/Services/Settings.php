<?php

namespace App\Services;

use App\Models\Setting;

class Settings
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : $value);
        }
    }

    public function allPublic(): array
    {
        return [
            'site_name' => $this->get('site_name', 'nozu.me'),
            'site_description' => $this->get('site_description', 'nozu.me, Türkçe anime ve manga keşif veritabanıdır.'),
            'logo_path' => $this->get('logo_path'),
            'favicon_path' => $this->get('favicon_path'),
            'translation_provider' => $this->get('translation_provider', config('services.translation.provider', 'azure')),
            'deepl_enabled' => $this->get('deepl_enabled', '0') === '1',
            'google_translate_enabled' => $this->get('google_translate_enabled', '0') === '1',
            'gemini_enabled' => $this->get('gemini_enabled', '0') === '1',
        ];
    }
}
