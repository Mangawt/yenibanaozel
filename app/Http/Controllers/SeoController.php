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
            'Disallow: /adminasip',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('search', ['type' => 'anime']), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('search', ['type' => 'manga']), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('api.docs'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => route('about'), 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        $people = [];
        foreach (Media::query()->latest('updated_at')->get() as $media) {
            $urls[] = [
                'loc' => route('media.show', ['type' => $media->type, 'media' => $media]),
                'lastmod' => $media->updated_at?->toAtomString(),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ];

            foreach (($media->characters ?? []) as $character) {
                if (filled($character['voice_actor'] ?? null)) {
                    $people[Str::slug($character['voice_actor'])] = true;
                }
            }

            foreach (($media->staff ?? []) as $staff) {
                if (filled($staff['name'] ?? null)) {
                    $people[Str::slug($staff['name'])] = true;
                }
            }
        }

        foreach (array_keys($people) as $slug) {
            $urls[] = [
                'loc' => route('people.show', ['slug' => $slug]),
                'priority' => '0.5',
                'changefreq' => 'weekly',
            ];
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
