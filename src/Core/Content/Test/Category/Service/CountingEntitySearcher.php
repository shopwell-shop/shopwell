<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Test\Category\Service;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * @internal
 */
class CountingEntitySearcher implements EntitySearcherInterface
{
    /**
     * @var int[]
     */
    private static array $count = [];

    public function __construct(private readonly EntitySearcherInterface $inner)
    {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        static::$count[$definition->getEntityName()] ??= 0 + 1;

        return $this->inner->search($definition, $criteria, $context);
    }

    public static function resetCount(): void
    {
        static::$count = [];
    }

    public static function getSearchOperationCount(string $entityName): int
    {
        return static::$count[$entityName] ?? 0;
    }
}
