<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\TypeDetector;

use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaType\MediaType;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class TypeDetector implements TypeDetectorInterface
{
    /**
     * @internal
     *
     * @param TypeDetectorInterface[] $typeDetector
     */
    public function __construct(private readonly iterable $typeDetector)
    {
    }

    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType = null): MediaType
    {
        $mediaType = null;
        foreach ($this->typeDetector as $typeDetector) {
            $mediaType = $typeDetector->detect($mediaFile, $mediaType);
        }

        return $mediaType;
    }
}
