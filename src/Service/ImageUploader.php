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

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public function validate(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return 'The photo could not be uploaded. Please try a smaller image.';
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
        $extension = self::ALLOWED[(string) $file->getMimeType()];
        $name = bin2hex(random_bytes(8)) . '.' . $extension;
        $dir = $this->projectDir . '/public/uploads/' . $folder;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file->move($dir, $name);

        return 'uploads/' . $folder . '/' . $name;
    }
}