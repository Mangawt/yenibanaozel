<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UserMediaStorage
{
    private const BUNNY_PREFIX = 'bunny:';

    /**
     * Avatarı Bunny Storage'a yükler.
     *
     * Veritabanına yazılabilecek değer:
     * bunny:users/{userId}/avatar/{uuid}.{extension}
     */
    public function uploadAvatar(
        UploadedFile $file,
        int $userId,
    ): string {
        return $this->upload(
            file: $file,
            directory: $this->userDirectory(
                $userId,
                'avatar',
            ),
        );
    }

    /**
     * Kapak görselini Bunny Storage'a yükler.
     */
    public function uploadBanner(
        UploadedFile $file,
        int $userId,
    ): string {
        return $this->upload(
            file: $file,
            directory: $this->userDirectory(
                $userId,
                'banner',
            ),
        );
    }

    /**
     * Yerel, Bunny veya tam URL biçimindeki yolu
     * kullanıcıya gösterilecek URL'ye dönüştürür.
     */
    public function url(
        mixed $value,
    ): ?string {
        $path = $this->normalizeValue($value);

        if ($path === null) {
            return null;
        }

        if ($this->isHttpUrl($path)) {
            return $path;
        }

        if ($this->isBunnyPath($path)) {
            $cdnUrl = $this->cdnUrl();

            return $cdnUrl.'/'.implode(
                '/',
                array_map(
                    'rawurlencode',
                    explode(
                        '/',
                        $this->bunnyRelativePath($path),
                    ),
                ),
            );
        }

        return asset(
            'storage/'.$this->localRelativePath(
                $path,
            ),
        );
    }

    /**
     * Hem eski yerel dosyaları hem yeni Bunny dosyaların siler.
     *
     * Tam dış URL'ler silinmez. Örneğin Google avatarı.
     */
    public function delete(
        mixed $value,
    ): void {
        $path = $this->normalizeValue($value);

        if ($path === null) {
            return;
        }

        if ($this->isHttpUrl($path)) {
            return;
        }

        if ($this->isBunnyPath($path)) {
            $this->deleteFromBunny(
                $this->bunnyRelativePath($path),
            );

            return;
        }

        Storage::disk('public')->delete(
            $this->localRelativePath($path),
        );
    }

    public function isBunnyPath(
        mixed $value,
    ): bool {
        $path = $this->normalizeValue($value);

        return $path !== null
            && str_starts_with(
                $path,
                self::BUNNY_PREFIX,
            );
    }

    public function isLocalPath(
        mixed $value,
    ): bool {
        $path = $this->normalizeValue($value);

        return $path !== null
            && ! $this->isHttpUrl($path)
            && ! $this->isBunnyPath($path);
    }

    private function upload(
        UploadedFile $file,
        string $directory,
    ): string {
        $this->assertConfigured();

        $realPath = $file->getRealPath();

        if (
            $realPath === false
            || ! is_file($realPath)
            || ! is_readable($realPath)
        ) {
            throw new RuntimeException(
                'Yüklenen geçici dosya okunamadı.',
            );
        }

        $extension = $this->safeExtension(
            $file,
        );

        $fileName = Str::uuid()->toString().
            '.'.$extension;

        $relativePath = trim(
            $directory.'/'.$fileName,
            '/',
        );

        $contents = file_get_contents(
            $realPath,
        );

        if ($contents === false) {
            throw new RuntimeException(
                'Yüklenen dosyanın içeriği okunamadı.',
            );
        }

        $mimeType = trim(
            (string) (
                $file->getMimeType()
                ?: 'application/octet-stream'
            ),
        );

        try {
            $response = Http::connectTimeout(
                $this->connectTimeout(),
            )
                ->timeout(
                    $this->requestTimeout(),
                )
                ->retry(
                    2,
                    500,
                    throw: false,
                )
                ->withHeaders([
                    'AccessKey' =>
                        $this->storagePassword(),

                    'Content-Type' =>
                        $mimeType,

                    'Checksum' => strtoupper(
                        hash(
                            'sha256',
                            $contents,
                        ),
                    ),
                ])
                ->withBody(
                    $contents,
                    $mimeType,
                )
                ->put(
                    $this->storageApiUrl(
                        $relativePath,
                    ),
                );
        } catch (Throwable $exception) {
            Log::error(
                'Bunny user media upload request failed.',
                [
                    'path' => $relativePath,
                    'exception' =>
                        $exception->getMessage(),
                ],
            );

            throw new RuntimeException(
                'Görsel depolama servisine bağlanılamadı.',
                previous: $exception,
            );
        }

        if ($response->status() !== 201) {
            Log::error(
                'Bunny user media upload failed.',
                [
                    'path' => $relativePath,
                    'status' => $response->status(),
                    'response' => Str::limit(
                        $response->body(),
                        1000,
                    ),
                ],
            );

            throw new RuntimeException(
                'Görsel Bunny Storage alanına yüklenemedi.',
            );
        }

        return self::BUNNY_PREFIX.
            $relativePath;
    }

    private function deleteFromBunny(
        string $relativePath,
    ): void {
        $this->assertConfigured();

        try {
            $response = Http::connectTimeout(
                $this->connectTimeout(),
            )
                ->timeout(
                    $this->requestTimeout(),
                )
                ->retry(
                    2,
                    500,
                    throw: false,
                )
                ->withHeaders([
                    'AccessKey' =>
                        $this->storagePassword(),
                ])
                ->delete(
                    $this->storageApiUrl(
                        $relativePath,
                    ),
                );
        } catch (Throwable $exception) {
            Log::warning(
                'Bunny user media delete request failed.',
                [
                    'path' => $relativePath,
                    'exception' =>
                        $exception->getMessage(),
                ],
            );

            return;
        }

        /*
         * Bunny başarılı silmede 200 döndürür.
         * 404 dosyanın zaten bulunmadığı anlamına gelir;
         * bu durumda kullanıcı işlemini başarısız saymıyoruz.
         */
        if (
            ! in_array(
                $response->status(),
                [200, 404],
                true,
            )
        ) {
            Log::warning(
                'Bunny user media could not be deleted.',
                [
                    'path' => $relativePath,
                    'status' => $response->status(),
                    'response' => Str::limit(
                        $response->body(),
                        1000,
                    ),
                ],
            );
        }
    }

    private function userDirectory(
        int $userId,
        string $type,
    ): string {
        if ($userId <= 0) {
            throw new RuntimeException(
                'Geçersiz kullanıcı kimliği.',
            );
        }

        $prefix = trim(
            (string) config(
                'bunny.path_prefix',
                'users',
            ),
            '/',
        );

        return trim(
            $prefix.'/'.
            $userId.'/'.
            trim($type, '/'),
            '/',
        );
    }

    private function safeExtension(
        UploadedFile $file,
    ): string {
        $extension = mb_strtolower(
            trim(
                (string) (
                    $file->extension()
                    ?: $file->getClientOriginalExtension()
                ),
            ),
        );

        return match ($extension) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',

            default => throw new RuntimeException(
                'Desteklenmeyen görsel uzantısı.',
            ),
        };
    }

    private function storageApiUrl(
        string $relativePath,
    ): string {
        $segments = array_filter(
            explode(
                '/',
                trim($relativePath, '/'),
            ),
            static fn (
                string $segment,
            ): bool => $segment !== '',
        );

        $encodedPath = implode(
            '/',
            array_map(
                'rawurlencode',
                $segments,
            ),
        );

        return $this->storageEndpoint().
            '/'.
            rawurlencode(
                $this->storageZone(),
            ).
            '/'.
            $encodedPath;
    }

    private function normalizeValue(
        mixed $value,
    ): ?string {
        $path = trim(
            (string) $value,
        );

        return $path !== ''
            ? $path
            : null;
    }

    private function bunnyRelativePath(
        string $path,
    ): string {
        return ltrim(
            substr(
                $path,
                strlen(self::BUNNY_PREFIX),
            ),
            '/',
        );
    }

    private function localRelativePath(
        string $path,
    ): string {
        $path = ltrim(
            $path,
            '/',
        );

        if (
            str_starts_with(
                $path,
                'storage/',
            )
        ) {
            $path = substr(
                $path,
                strlen('storage/'),
            );
        }

        return ltrim(
            $path,
            '/',
        );
    }

    private function isHttpUrl(
        string $path,
    ): bool {
        return str_starts_with(
            mb_strtolower($path),
            'http://',
        ) || str_starts_with(
            mb_strtolower($path),
            'https://',
        );
    }

    private function assertConfigured(): void
    {
        if (
            $this->storageZone() === ''
            || $this->storagePassword() === ''
            || $this->storageEndpoint() === ''
            || $this->cdnUrl() === ''
        ) {
            throw new RuntimeException(
                'Bunny Storage yapılandırması eksik.',
            );
        }
    }

    private function storageZone(): string
    {
        return trim(
            (string) config(
                'bunny.storage_zone',
                '',
            ),
        );
    }

    private function storagePassword(): string
    {
        return trim(
            (string) config(
                'bunny.storage_password',
                '',
            ),
        );
    }

    private function storageEndpoint(): string
    {
        return rtrim(
            trim(
                (string) config(
                    'bunny.storage_endpoint',
                    '',
                ),
            ),
            '/',
        );
    }

    private function cdnUrl(): string
    {
        return rtrim(
            trim(
                (string) config(
                    'bunny.cdn_url',
                    '',
                ),
            ),
            '/',
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            (int) config(
                'bunny.connect_timeout',
                10,
            ),
        );
    }

    private function requestTimeout(): int
    {
        return max(
            5,
            (int) config(
                'bunny.timeout',
                45,
            ),
        );
    }
}
