<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Metadata\MetadataLoader;

use Shopwell\Core\Content\Media\MediaType\MediaType;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface MetadataLoaderInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function extractMetadata(string $filePath): ?array;

    public function supports(MediaType $mediaType): bool;
}
