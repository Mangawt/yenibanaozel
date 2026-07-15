<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use App\Support\Seo;

class PageController extends Controller
{
    public function about(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.about',
            'Hakkımızda - nozu.me',
            'nozu.me hakkında: Türkçe anime ve manga keşif arşivi, tanıtım amaçlı içerik politikası ve telif açıklaması.',
            route('about')
        );
    }

    public function privacy(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.privacy',
            'Gizlilik Politikası ve KVKK - nozu.me',
            'nozu.me gizlilik politikası, KVKK aydınlatma metni, çerezler, günlük kayıtları ve üçüncü taraf bağlantılar.',
            route('privacy')
        );
    }

    public function terms(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.terms',
            'Kullanım Şartları - nozu.me',
            'nozu.me kullanım şartları, hesap kuralları, API kullanımı ve platform sorumlulukları.',
            route('terms')
        );
    }

    public function cookies(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.cookies',
            'Çerez Politikası - nozu.me',
            'nozu.me çerez politikası, zorunlu çerezler, tercih çerezleri ve çerez tercihleri.',
            route('cookies')
        );
    }

    public function copyright(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.copyright',
            'Telif ve İçerik Kaldırma - nozu.me',
            'nozu.me telif hakkı ve içerik kaldırma başvuru politikası.',
            route('copyright')
        );
    }

    public function disclaimer(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.disclaimer',
            'Sorumluluk Reddi - nozu.me',
            'nozu.me üzerinde sunulan anime ve manga bilgilerinin kapsamı ve sorumluluk reddi.',
            route('disclaimer')
        );
    }

    public function contact(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.contact',
            'İletişim - nozu.me',
            'nozu.me destek, düzeltme, öneri ve telif bildirimi iletişim bilgileri.',
            route('contact')
        );
    }

    public function cookiePreferences(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.cookie-preferences',
            'Çerez Tercihleri - nozu.me',
            'nozu.me çerez tercihleri ve kullanıcı izin yönetimi.',
            route('cookie-preferences')
        );
    }

    private function legalView(Settings $settings, string $view, string $title, string $description, string $canonical)
    {
        return view($view, [
            'settings' => $settings->allPublic(),
            'seo' => Seo::defaults([
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
            ]),
        ]);
    }
}
