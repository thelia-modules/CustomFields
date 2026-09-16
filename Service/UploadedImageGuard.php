<?php

declare(strict_types=1);

namespace CustomFields\Service;

use CustomFields\CustomFields;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Translation\Translator;

/**
 * Decides, from the bytes of a submitted file alone, whether it is stored and
 * under which name.
 *
 * Custom field pictures are copied into the public cache as they are, so the
 * name they are stored under is the name the site serves. Neither the name
 * nor the extension sent by the browser is reused: the format is read from
 * the content, the extension comes from that format, and the name is
 * generated.
 */
final class UploadedImageGuard
{
    private const EXTENSION_BY_MIME_TYPE = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
    ];

    /**
     * An SVG is a document the browser runs: it is stored only when it holds
     * none of the constructs that make it one.
     */
    private const REFUSED_IN_SVG = [
        '/<\s*script/i',
        '/<\s*foreignObject/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
        '/<!ENTITY/i',
        '/<\s*(iframe|embed|object)/i',
    ];

    public function generateFileName(File $file): string
    {
        $mimeType = $file->getMimeType();

        if (null === $mimeType || !isset(self::EXTENSION_BY_MIME_TYPE[$mimeType])) {
            throw new \RuntimeException($this->refusalMessage());
        }

        if ('image/svg+xml' === $mimeType) {
            $this->assertPlainDrawing($file);
        } else {
            $dimensions = @getimagesize($file->getPathname());

            if (false === $dimensions || !isset($dimensions[2]) || image_type_to_mime_type($dimensions[2]) !== $mimeType) {
                throw new \RuntimeException($this->refusalMessage());
            }
        }

        return bin2hex(random_bytes(10)).'.'.self::EXTENSION_BY_MIME_TYPE[$mimeType];
    }

    private function assertPlainDrawing(File $file): void
    {
        $contents = (string) file_get_contents($file->getPathname());

        foreach (self::REFUSED_IN_SVG as $pattern) {
            if (1 === preg_match($pattern, $contents)) {
                throw new \RuntimeException($this->refusalMessage());
            }
        }
    }

    private function refusalMessage(): string
    {
        return Translator::getInstance()->trans(
            'Only JPEG, PNG, GIF, WebP and plain SVG images can be used here.',
            [],
            CustomFields::DOMAIN_NAME
        );
    }
}
