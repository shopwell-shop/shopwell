<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\TermLevel\ExistsQuery;
use OpenSearchDSL\Query\TermLevel\RangeQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopwell\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\CustomFieldService;
use Shopwell\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

/**
 * @internal - This class is part of the internal API, optimized for read and should not be used directly.
 */
#[Package('inventory')]
class ProductCriteriaParser extends CriteriaParser
{
    public function __construct(
        EntityDefinitionQueryHelper $helper,
        CustomFieldService $customFieldService,
        private readonly AbstractKeyValueStorage $storage,
        private readonly CriteriaParser $decorated
    ) {
        parent::__construct($helper, $customFieldService, $storage);
    }

    public function parseFilter(Filter $filter, EntityDefinition $definition, string $root, Context $context): BuilderInterface
    {
        if (!$definition instanceof ProductDefinition) {
            return parent::parseFilter($filter, $definition, $root, $context);
        }

        if ($filter instanceof ProductAvailableFilter) {
            /**
             * @deprecated tag:v6.8.0 - this if statement will be always true
             */
            if (!Feature::isActive('v6.8.0.0') && !$this->storage->has(ElasticsearchOptimizeSwitch::FLAG)) {
                return $this->decorated->parseFilter($filter, $definition, $root, $context);
            }

            $query = new BoolQuery();

            // Shopwell\Administration\Controller\AdminProductStreamController::productStreamPreview() add ProductAvailableFilter
            // but remove active filter, so we need to check if active filter is added to the query
            foreach ($filter->getQueries() as $subFilter) {
                if ($subFilter instanceof EqualsFilter && \in_array($subFilter->getField(), ['product.active', 'active'], true)) {
                    $query->add(
                        new TermQuery('active', true),
                    );

                    break;
                }
            }

            $query->add(
                new RangeQuery('visibility_' . $filter->getSalesChannelId(), [RangeFilter::GTE => $filter->getVisibility()]),
            );

            return $query;
        }

        if ($filter instanceof EqualsFilter && \str_contains($filter->getField(), 'categoriesRo.id')) {
            /**
             * @deprecated tag:v6.8.0 - this if statement will be always true
             */
            if (!Feature::isActive('v6.8.0.0') && !$this->storage->has(ElasticsearchOptimizeSwitch::FLAG)) {
                return $this->decorated->parseFilter($filter, $definition, $root, $context);
            }

            if ($filter->getValue() === null) {
                return new BoolQuery([
                    BoolQuery::MUST_NOT => new ExistsQuery('categoryTree'),
                ]);
            }

            return new TermQuery('categoryTree', $filter->getValue());
        }

        if ($filter instanceof EqualsAnyFilter && \str_contains($filter->getField(), 'categoriesRo.id')) {
            /**
             * @deprecated tag:v6.8.0 - this if statement will be always true
             */
            if (!Feature::isActive('v6.8.0.0') && !$this->storage->has(ElasticsearchOptimizeSwitch::FLAG)) {
                return $this->decorated->parseFilter($filter, $definition, $root, $context);
            }

            return new TermsQuery('categoryTree', array_values($filter->getValue()));
        }

        return parent::parseFilter($filter, $definition, $root, $context);
    }
}
