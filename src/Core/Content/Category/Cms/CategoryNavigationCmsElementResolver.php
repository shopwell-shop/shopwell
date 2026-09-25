<?php

declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Cms;

use Shopwell\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNavigationCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getType(): string
    {
        return 'category-navigation';
    }

    /**
     * @codeCoverageIgnore
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $salesChannelContext = $resolverContext->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $rootNavigationId = $salesChannel->getNavigationCategoryId();
        $request = $resolverContext->getRequest();
        $navigationId = (string) ($request->attributes->get('navigationId') ?? RequestParamHelper::get($request, 'navigationId', $rootNavigationId));

        $tree = $this->navigationLoader->load(
            $navigationId,
            $salesChannelContext,
            $rootNavigationId,
            $salesChannel->getNavigationCategoryDepth()
        );

        $slot->setData($tree);
    }
}
