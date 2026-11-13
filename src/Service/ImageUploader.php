<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploader
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_WIDTH = 1200;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function validate(UploadedFile $file): ?string
    {
                if (!$file->isValid()) {
            if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                return 'That photo is larger than the server allows. Try one under 5 MB.';
            }

            return 'The photo could not be uploaded. Please try again.';
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return 'The photo must be 5 MB or smaller.';
        }

        if (!isset(self::ALLOWED[(string) $file->getMimeType()])) {
            return 'The photo must be a JPG, PNG or WEBP image.';
        }

        return null;
    }

    /** Saves the file and returns its path relative to /public. */
    public function save(UploadedFile $file, string $folder): string
    {
        $mime = (string) $file->getMimeType();
        $extension = self::ALLOWED[$mime];
        $name = bin2hex(random_bytes(8)) . '.' . $extension;
        $dir = $this->projectDir . '/public/uploads/' . $folder;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file->move($dir, $name);
        $this->shrink($dir . '/' . $name, $mime);

        return 'uploads/' . $folder . '/' . $name;
    }

    /** Removes a previously saved upload. Only touches files inside public/uploads. */
    public function delete(?string $relativePath): void
    {
        if (!$relativePath || !str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return;
        }

        $full = $this->projectDir . '/public/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    /** Scales big photos down so a 5 MB phone picture does not sit on disk untouched. Needs the GD extension, otherwise it is skipped. */
    private function shrink(string $path, string $mime): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $size = @getimagesize($path);
                // Decoding a huge image can exhaust PHP's memory, so skip resizing those.
        if ($size[0] * $size[1] > 12_000_000) {
            return;
        }

        [$width, $height] = $size;
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (!$source) {
            return;
        }

        $newHeight = (int) round($height * (self::MAX_WIDTH / $width));
        $resized = imagecreatetruecolor(self::MAX_WIDTH, $newHeight);
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $source, 0, 0, 0, 0, self::MAX_WIDTH, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($resized, $path, 85),
            'image/png' => imagepng($resized, $path, 6),
            'image/webp' => imagewebp($resized, $path, 85),
            default => null,
        };
    }
}
