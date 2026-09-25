<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Product\SearchFieldConfig;

#[Package('inventory')]
abstract class AbstractFieldQueryBuilder
{
    abstract public function getDecorated(): self;

    abstract public function build(
        ResolvedField $field,
        string $token,
        SearchFieldConfig $config,
        Context $context,
    ): ?BuilderInterface;
}
