<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemElementTypeRegistry
{
    abstract public function getDecorated(): AbstractContentSystemElementTypeRegistry;

    /**
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    abstract public function all(): array;

    abstract public function has(string $name): bool;

    abstract public function get(string $name): ContentSystemElementTypeSpecification;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
