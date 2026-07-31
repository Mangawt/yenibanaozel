<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Media;
use App\Models\Person;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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


    public function accountDeletion(Settings $settings)
    {
        return $this->legalView(
            $settings,
            'pages.account-deletion',
            'Hesap ve Veri Silme - nozu.me',
            'Nozu hesabı ve bağlantılı kullanıcı verileri için hesap silme talebi, silinen veriler ve veri saklama bilgileri.',
            route('account-deletion')
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

    public function siteStats(Settings $settings)
    {
        $stats = [
            $this->statsSeries(
                'Anime',
                Media::query()->where('type', 'anime'),
                'fa-solid fa-tv',
            ),
            $this->statsSeries(
                'Manga',
                Media::query()->where('type', 'manga'),
                'fa-solid fa-book-open',
            ),
            $this->statsSeries(
                'Karakterler',
                Character::query(),
                'fa-regular fa-user',
            ),
            $this->statsSeries(
                'Kişiler ve ekip',
                Person::query(),
                'fa-solid fa-user-group',
            ),
        ];

        return view('pages.site-stats', [
            'settings' => $settings->allPublic(),
            'stats' => $stats,
            'seo' => Seo::defaults([
                'title' => 'Site İstatistikleri - nozu.me',
                'description' => 'Nozu anime, manga, karakter ve kişi katalog istatistikleri.',
                'canonical' => route('site-stats'),
            ]),
        ]);
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

    private function statsSeries(string $label, Builder $query, string $icon): array
    {
        $dates = collect(range(14, 0))
            ->map(fn (int $daysAgo) => now()->copy()->subDays($daysAgo)->startOfDay());

        $values = $dates
            ->map(function (Carbon $date) use ($query): int {
                $dayQuery = clone $query;

                return (int) $dayQuery
                    ->where('created_at', '<=', $date->copy()->endOfDay())
                    ->count();
            })
            ->values();

        $total = (int) (clone $query)->count();
        $previous = $values->count() > 1 ? (int) $values[$values->count() - 2] : $total;
        $delta = max(0, $total - $previous);
        $min = (int) $values->min();
        $max = (int) $values->max();
        $range = max(1, $max - $min);
        $step = $values->count() > 1 ? 100 / ($values->count() - 1) : 100;

        $points = $values
            ->map(function (int $value, int $index) use ($min, $range, $step): string {
                $x = round($index * $step, 2);
                $y = round(86 - (($value - $min) / $range * 58), 2);

                return $x.','.$y;
            })
            ->implode(' ');

        return [
            'label' => $label,
            'icon' => $icon,
            'total' => $total,
            'total_label' => $this->compactNumber($total),
            'delta' => $delta,
            'dates' => $dates
                ->map(fn (Carbon $date) => $date->translatedFormat('j M'))
                ->all(),
            'values' => $values->all(),
            'points' => $points,
        ];
    }

    private function compactNumber(int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1, ',', '.').'M';
        }

        if ($value >= 1000) {
            return number_format($value / 1000, 1, ',', '.').'B';
        }

        return number_format($value, 0, ',', '.');
    }
}
