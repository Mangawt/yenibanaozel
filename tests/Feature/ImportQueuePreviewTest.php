<?php

namespace Tests\Feature;

use App\Services\ImportQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportQueuePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_anilist_links_keep_their_url_type_in_preview(): void
    {
        $preview = app(ImportQueueService::class)->preview([
            'source' => 'anilist',
            'type' => 'anime',
            'links' => implode(PHP_EOL, [
                'https://anilist.co/anime/20/naruto',
                'https://anilist.co/manga/30013/one-piece',
            ]),
        ]);

        $this->assertSame(2, $preview['found']);
        $this->assertSame(1, $preview['anime']);
        $this->assertSame(1, $preview['manga']);
        $this->assertSame([
            ['source' => 'anilist', 'type' => 'anime', 'id' => 20],
            ['source' => 'anilist', 'type' => 'manga', 'id' => 30013],
        ], $preview['entries']);
    }
}
