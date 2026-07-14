<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use App\Support\Seo;

class PageController extends Controller
{
    public function about(Settings $settings)
    {
        return view('pages.about', [
            'settings' => $settings->allPublic(),
            'seo' => Seo::defaults([
                'title' => 'Hakkımızda - nozu.me',
                'description' => 'nozu.me hakkında: Türkçe anime ve manga keşif arşivi, tanıtım amaçlı içerik politikası ve telif açıklaması.',
                'canonical' => route('about'),
            ]),
        ]);
    }

    public function privacy(Settings $settings)
    {
        return view('pages.privacy', [
            'settings' => $settings->allPublic(),
            'seo' => Seo::defaults([
                'title' => 'Gizlilik Politikası - nozu.me',
                'description' => 'nozu.me gizlilik politikası, çerezler, günlük kayıtları, üçüncü taraf bağlantılar ve tanıtım amaçlı içerik açıklaması.',
                'canonical' => route('privacy'),
            ]),
        ]);
    }
}
