<?php

namespace App\Support;

class AnimeLabels
{
    public const GENRES = [
        'Action' => 'Aksiyon',
        'Adventure' => 'Macera',
        'Comedy' => 'Komedi',
        'Drama' => 'Dram',
        'Ecchi' => 'Ecchi',
        'Fantasy' => 'Fantastik',
        'Hentai' => 'Hentai',
        'Horror' => 'Korku',
        'Mahou Shoujo' => 'Büyülü Kız',
        'Mecha' => 'Mecha',
        'Music' => 'Müzik',
        'Mystery' => 'Gizem',
        'Psychological' => 'Psikolojik',
        'Romance' => 'Romantik',
        'Sci-Fi' => 'Bilim Kurgu',
        'Slice of Life' => 'Gündelik Yaşam',
        'Sports' => 'Spor',
        'Supernatural' => 'Doğaüstü',
        'Thriller' => 'Gerilim',
    ];

    public const FORMATS = [
        'TV' => 'TV',
        'TV_SHORT' => 'Kısa TV',
        'MOVIE' => 'Film',
        'SPECIAL' => 'Özel Bölüm',
        'OVA' => 'OVA',
        'ONA' => 'ONA',
        'MUSIC' => 'Müzik',
        'MANGA' => 'Manga',
        'NOVEL' => 'Roman',
        'ONE_SHOT' => 'Tek Bölüm',
        'LIGHT_NOVEL' => 'Light Novel',
    ];

    public const STATUSES = [
        'FINISHED' => 'Tamamlandı',
        'RELEASING' => 'Yayınlanıyor',
        'NOT_YET_RELEASED' => 'Henüz yayınlanmadı',
        'CANCELLED' => 'İptal edildi',
        'HIATUS' => 'Ara verildi',
    ];

    public const SEASONS = [
        'WINTER' => 'Kış',
        'SPRING' => 'İlkbahar',
        'SUMMER' => 'Yaz',
        'FALL' => 'Sonbahar',
    ];

    public const RELATIONS = [
        'ADAPTATION' => 'Uyarlama',
        'PREQUEL' => 'Öncesi',
        'SEQUEL' => 'Devamı',
        'PARENT' => 'Ana seri',
        'SIDE_STORY' => 'Yan hikaye',
        'CHARACTER' => 'Karakter hikayesi',
        'SUMMARY' => 'Özet',
        'ALTERNATIVE' => 'Alternatif',
        'SPIN_OFF' => 'Yan seri',
        'OTHER' => 'Diğer',
        'SOURCE' => 'Kaynak',
        'COMPILATION' => 'Derleme',
        'CONTAINS' => 'İçerir',
    ];

    public const STAFF_ROLES = [
        'Original Creator' => 'Orijinal Yaratıcı',
        'Original Story' => 'Orijinal Hikaye',
        'Original Character Design' => 'Orijinal Karakter Tasarımı',
        'Director' => 'Yönetmen',
        'Assistant Director' => 'Yardımcı Yönetmen',
        'Character Design' => 'Karakter Tasarımı',
        'Art Director' => 'Sanat Yönetmeni',
        'Chief Animation Director' => 'Baş Animasyon Yönetmeni',
        'Animation Director' => 'Animasyon Yönetmeni',
        'Director of Photography' => 'Görüntü Yönetmeni',
        'Sound Director' => 'Ses Yönetmeni',
        'Sound Effects' => 'Ses Efektleri',
        'Music' => 'Müzik',
        'Producer' => 'Yapımcı',
        'Animation Producer' => 'Animasyon Yapımcısı',
        'Series Composition' => 'Seri Kompozisyonu',
        'Script' => 'Senaryo',
        'Storyboard' => 'Storyboard',
        'Episode Director' => 'Bölüm Yönetmeni',
        'Color Design' => 'Renk Tasarımı',
        'Editing' => 'Kurgu',
        'Video Editing' => 'Video Kurgu',
        'Offline Editing' => 'Offline Kurgu',
    ];

    public const RANKING_CONTEXTS = [
        'highest rated all time' => 'Tüm zamanların en yüksek puanlıları',
        'most popular all time' => 'Tüm zamanların en popülerleri',
        'highest rated' => 'En yüksek puanlı',
        'most popular' => 'En popüler',
    ];

    public static function genre(?string $value): ?string
    {
        return $value ? (self::GENRES[$value] ?? $value) : null;
    }

    public static function format(?string $value): ?string
    {
        return $value ? (self::FORMATS[$value] ?? $value) : null;
    }

    public static function status(?string $value): ?string
    {
        return $value ? (self::STATUSES[$value] ?? $value) : null;
    }

    public static function season(?string $value): ?string
    {
        return $value ? (self::SEASONS[$value] ?? $value) : null;
    }

    public static function relation(?string $value): ?string
    {
        return $value ? (self::RELATIONS[$value] ?? $value) : null;
    }

    public static function staffRole(?string $value): ?string
    {
        return $value ? (self::STAFF_ROLES[$value] ?? $value) : null;
    }

    public static function rankingContext(?string $value): ?string
    {
        return $value ? (self::RANKING_CONTEXTS[strtolower($value)] ?? $value) : null;
    }
}
