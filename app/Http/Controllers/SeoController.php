<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoController extends Controller
{
    private const SITEMAP_PAGE_SIZE = 25000;

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /adminasip',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function sitemapIndex(): StreamedResponse
    {
        $counts = DB::table('media')
            ->selectRaw('type, COUNT(*) AS total')
            ->whereIn('type', ['anime', 'manga'])
            ->groupBy('type')
            ->pluck('total', 'type');

        $sitemaps = [
            url('/sitemaps/static.xml'),
        ];

        foreach (['anime', 'manga'] as $type) {
            $total = (int) ($counts[$type] ?? 0);
            $pageCount = (int) ceil($total / self::SITEMAP_PAGE_SIZE);

            for ($page = 1; $page <= $pageCount; $page++) {
                $sitemaps[] = url("/sitemaps/{$type}-{$page}.xml");
            }
        }

        return response()->stream(function () use ($sitemaps): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($sitemaps as $sitemapUrl) {
                echo '<sitemap>';
                echo '<loc>'.$this->escapeXml($sitemapUrl).'</loc>';
                echo '</sitemap>'."\n";
            }

            echo '</sitemapindex>';
        }, 200, $this->xmlHeaders());
    }

    public function sitemapStatic(): StreamedResponse
    {
        $staticUrls = [
            [route('home'), 'daily', '1.0'],
            [route('search', ['type' => 'anime']), 'daily', '0.8'],
            [route('search', ['type' => 'manga']), 'daily', '0.8'],
            [route('characters.index'), 'weekly', '0.7'],
            [route('people.index'), 'weekly', '0.7'],
            [route('studios.index'), 'weekly', '0.7'],
            [route('api.docs'), 'monthly', '0.5'],
            [route('about'), 'monthly', '0.4'],
            [route('privacy'), 'yearly', '0.3'],
            [route('terms'), 'yearly', '0.3'],
            [route('cookies'), 'yearly', '0.3'],
            [route('copyright'), 'yearly', '0.3'],
            [route('disclaimer'), 'yearly', '0.3'],
            [route('contact'), 'monthly', '0.3'],
        ];

        return response()->stream(function () use ($staticUrls): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($staticUrls as [$loc, $changefreq, $priority]) {
                echo '<url>';
                echo '<loc>'.$this->escapeXml($loc).'</loc>';
                echo '<changefreq>'.$changefreq.'</changefreq>';
                echo '<priority>'.$priority.'</priority>';
                echo '</url>'."\n";
            }

            echo '</urlset>';
        }, 200, $this->xmlHeaders());
    }

    public function sitemapMedia(string $type, int $page): StreamedResponse
    {
        abort_unless(in_array($type, ['anime', 'manga'], true), 404);
        abort_if($page < 1, 404);

        $total = Media::query()
            ->where('type', $type)
            ->count();

        $pageCount = (int) ceil($total / self::SITEMAP_PAGE_SIZE);

        abort_if($pageCount === 0 || $page > $pageCount, 404);

        $offset = ($page - 1) * self::SITEMAP_PAGE_SIZE;

        return response()->stream(function () use ($type, $offset): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            DB::table('media')
                ->select([
                    'id',
                    'slug',
                    'type',
                    'updated_at',
                ])
                ->where('type', $type)
                ->orderBy('id')
                ->offset($offset)
                ->limit(self::SITEMAP_PAGE_SIZE)
                ->get()
                ->each(function (object $media): void {
                    $loc = route('media.show', [
                        'type' => $media->type,
                        'media' => $media->slug,
                    ]);

                    echo '<url>';
                    echo '<loc>'.$this->escapeXml($loc).'</loc>';

                    if (! empty($media->updated_at)) {
                        echo '<lastmod>'
                            .$this->escapeXml(
                                date(DATE_ATOM, strtotime((string) $media->updated_at))
                            )
                            .'</lastmod>';
                    }

                    echo '<changefreq>weekly</changefreq>';
                    echo '<priority>0.7</priority>';
                    echo '</url>'."\n";
                });

            echo '</urlset>';
        }, 200, $this->xmlHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function xmlHeaders(): array
    {
        return [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }
}
