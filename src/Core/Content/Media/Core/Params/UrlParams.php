<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Core\Params;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class UrlParams extends Struct
{
    public function __construct(
        public readonly string $id,
        public readonly UrlParamsSource $source,
        public readonly string $path,
        public readonly ?\DateTimeInterface $updatedAt = null,
        public readonly ?string $mimeType = null,
    ) {
    }

    public static function fromMedia(Entity $entity): self
    {
        return new self(
            id: $entity->getUniqueIdentifier(),
            source: UrlParamsSource::MEDIA,
            path: $entity->get('path'),
            updatedAt: $entity->get('updatedAt') ?? $entity->get('createdAt'),
            mimeType: $entity->get('mimeType')
        );
    }

    public static function fromThumbnail(Entity $entity): self
    {
        return new self(
            id: $entity->getUniqueIdentifier(),
            source: UrlParamsSource::THUMBNAIL,
            path: $entity->get('path'),
            updatedAt: $entity->get('updatedAt') ?? $entity->get('createdAt')
        );
    }
}
