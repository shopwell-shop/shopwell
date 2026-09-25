<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Product\SearchFieldConfig;

#[Package('inventory')]
abstract class AbstractTokenQueryBuilder
{
    abstract public function getDecorated(): self;

    /**
     * @param SearchFieldConfig[] $configs
     */
    abstract public function build(string $entity, string $token, array $configs, Context $context): ?BuilderInterface;
}
