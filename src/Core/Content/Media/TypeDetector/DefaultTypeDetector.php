<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\TypeDetector;

use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaType\AudioType;
use Shopwell\Core\Content\Media\MediaType\BinaryType;
use Shopwell\Core\Content\Media\MediaType\ImageType;
use Shopwell\Core\Content\Media\MediaType\MediaType;
use Shopwell\Core\Content\Media\MediaType\VideoType;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class DefaultTypeDetector implements TypeDetectorInterface
{
    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType
    {
        if ($previouslyDetectedType !== null) {
            return $previouslyDetectedType;
        }

        $mime = explode('/', $mediaFile->getMimeType());

        return match ($mime[0]) {
            'image' => new ImageType(),
            'video' => new VideoType(),
            'audio' => new AudioType(),
            default => new BinaryType(),
        };
    }
}
