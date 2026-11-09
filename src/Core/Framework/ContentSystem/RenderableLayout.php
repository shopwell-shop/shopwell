<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem;

use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class RenderableLayout
{
    /**
     * @param list<StoredElement> $elements
     */
    private function __construct(
        public LayoutReference $reference,
        public array $elements,
    ) {
    }

    /**
     * @param list<StoredElement> $elements
     */
    public static function create(LayoutReference $reference, array $elements): self
    {
        return new self($reference, $elements);
    }

    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create(
            LayoutReference::fromEntity($entity),
            $entity->getLayout()
        );
    }
}
