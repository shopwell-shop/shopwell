<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\SalesChannel;

use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
interface SalesChannelCmsPageLoaderInterface
{
    /**
     * @param array<string, mixed>|null $config
     *
     * @return EntitySearchResult<CmsPageCollection>
     */
    public function load(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult;
}
