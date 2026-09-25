<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Product;

use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductSearchBuilderInterface $decorated,
        private readonly ElasticsearchHelper $helper,
        private readonly ProductDefinition $productDefinition,
        private readonly int $searchTermMaxLength = 300
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->helper->allowSearch($this->productDefinition, $context->getContext(), $criteria)) {
            $this->decorated->build($request, $criteria, $context);

            return;
        }

        $search = RequestParamHelper::get($request, 'search');

        $term = \is_array($search) ? implode(' ', $search) : (string) $search;

        $term = mb_substr(trim($term), 0, $this->searchTermMaxLength);

        if ($term === '') {
            throw RoutingException::missingRequestParameter('search');
        }

        // reset queries and set term to criteria.
        $criteria->resetQueries();

        // elasticsearch will interpret this on demand
        $criteria->setTerm($term);
    }
}
