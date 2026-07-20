<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SeoController extends Controller
{
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

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    public function sitemap()
    {
        return response()->stream(function (): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."
";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."
";

            $staticUrls = [
                [route('home'), 'daily', '1.0'],
                [route('search', ['type' => 'anime']), 'daily', '0.8'],
                [route('search', ['type' => 'manga']), 'daily', '0.8'],
                [route('api.docs'), 'monthly', '0.5'],
                [route('about'), 'monthly', '0.4'],
                [route('privacy'), 'yearly', '0.3'],
                [route('terms'), 'yearly', '0.3'],
                [route('cookies'), 'yearly', '0.3'],
                [route('copyright'), 'yearly', '0.3'],
                [route('disclaimer'), 'yearly', '0.3'],
                [route('contact'), 'monthly', '0.3'],
            ];

            foreach ($staticUrls as [$loc, $changefreq, $priority]) {
                echo '<url>';
                echo '<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>';
                echo '<changefreq>'.$changefreq.'</changefreq>';
                echo '<priority>'.$priority.'</priority>';
                echo '</url>'."
";
            }

            Media::query()
                ->select(['id', 'slug', 'type', 'updated_at'])
                ->orderBy('id')
                ->lazyById(500)
                ->each(function (Media $media): void {
                    $loc = route('media.show', [
                        'type' => $media->type,
                        'media' => $media,
                    ]);

                    echo '<url>';
                    echo '<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>';

                    if ($media->updated_at) {
                        echo '<lastmod>'.$media->updated_at->toAtomString().'</lastmod>';
                    }

                    echo '<changefreq>weekly</changefreq>';
                    echo '<priority>0.7</priority>';
                    echo '</url>'."
";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                });

            echo '</urlset>';
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
