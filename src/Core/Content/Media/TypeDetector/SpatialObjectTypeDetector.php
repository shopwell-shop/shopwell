<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\TypeDetector;

use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaType\MediaType;
use Shopwell\Core\Content\Media\MediaType\SpatialObjectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
#[Package('discovery')]
class SpatialObjectTypeDetector implements TypeDetectorInterface
{
    protected const SUPPORTED_FILE_EXTENSIONS = [
        'glb' => [],
    ];

    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType
    {
        $fileExtension = mb_strtolower($mediaFile->getFileExtension());
        if (!\array_key_exists($fileExtension, self::SUPPORTED_FILE_EXTENSIONS)) {
            return $previouslyDetectedType;
        }

        if ($previouslyDetectedType === null) {
            $previouslyDetectedType = new SpatialObjectType();
        }

        $previouslyDetectedType->addFlags(self::SUPPORTED_FILE_EXTENSIONS[$fileExtension]);

        return $previouslyDetectedType;
    }
}
