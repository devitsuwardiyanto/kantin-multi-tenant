<?php

namespace App\Modules\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * UC-13: foto menu dikompresi otomatis ke WebP (sisi terpanjang maks 800 px, kualitas 80) lalu
 * disimpan di disk `public` per tenant. Nama berkas acak (UUID), bukan nama asli dari klien.
 */
final class MenuPhotoStore
{
    public const MAX_SIDE = 800;

    public function store(UploadedFile $file, int $tenantId): string
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('Berkas bukan gambar yang valid.');
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $target = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagedestroy($source);
        if ($target === false) {
            throw new RuntimeException('Gambar gagal diperkecil.');
        }

        ob_start();
        imagewebp($target, null, 80);
        $webp = (string) ob_get_clean();
        imagedestroy($target);

        $path = "menus/{$tenantId}/".Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
