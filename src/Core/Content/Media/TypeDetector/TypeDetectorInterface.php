<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\TypeDetector;

use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaType\MediaType;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface TypeDetectorInterface
{
    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType;
}
