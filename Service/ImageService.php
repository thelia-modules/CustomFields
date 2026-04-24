<?php

namespace CustomFields\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;

class ImageService
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function imageProcess($customFieldImage, bool $useTheliaLibrary = false, $resizeMode = 'crop', $width = null, $height = null, $format = null): array
    {
        $fileUrl = $originalFileUrl = null;

        try {
            if (!!$customFieldImage && !$useTheliaLibrary && !empty($customFieldImage->getFile())) {
                $file = $customFieldImage->getFile();
                $imgSourcePath = $customFieldImage->getUploadDir() . DS . $file;

                // GD/Imagick cannot process SVGs — copy directly to the public cache
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
                    $cacheDir = THELIA_WEB_DIR . 'cache' . DS . 'images' . DS . 'customField';
                    if (!is_dir($cacheDir)) {
                        mkdir($cacheDir, 0777, true);
                    }
                    $cachedPath = $cacheDir . DS . $file;
                    if (!file_exists($cachedPath)) {
                        copy($imgSourcePath, $cachedPath);
                    }
                    $fileUrl = '/cache/images/customField/' . $file;
                    $originalFileUrl = $fileUrl;
                    return [$fileUrl, $originalFileUrl];
                }

                $event = new ImageEvent();

                switch ($resizeMode) {
                    case 'crop':
                        $resize_mode = Image::EXACT_RATIO_WITH_CROP;
                        break;
                    case 'borders':
                        $resize_mode = Image::EXACT_RATIO_WITH_BORDERS;
                        break;
                    case 'none':
                    default:
                        $resize_mode = Image::KEEP_IMAGE_RATIO;
                }

                if (null !== $width) {
                    $event->setWidth($width);
                }

                if (null !== $height) {
                    $event->setHeight($height);
                }

                $event->setResizeMode($resize_mode);

                if (null !== $format) {
                    $event->setFormat($format);
                }

                $event->setSourceFilepath($imgSourcePath)
                    ->setCacheSubdirectory('customField');

                // Dispatch image processing event
                $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

                $fileUrl = $event->getFileUrl();
                $originalFileUrl = $event->getOriginalFileUrl();
            }
        } catch (\Exception $e) {
            // Silently handle error
        }

        return [$fileUrl, $originalFileUrl];
    }
}
