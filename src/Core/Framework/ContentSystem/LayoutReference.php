<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class LayoutReference
{
    private function __construct(
        public string $id,
        public string $name,
        public ?string $version,
    ) {
    }

    public static function create(string $id, string $name, ?string $version): self
    {
        return new self($id, $name, $version);
    }

    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create($entity->getId(), $entity->getName(), $entity->getVersion());
    }
}
