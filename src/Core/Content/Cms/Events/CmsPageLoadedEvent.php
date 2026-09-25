<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Events;

use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class CmsPageLoadedEvent extends NestedEvent implements ShopwellSalesChannelEvent
{
    protected CmsPageCollection $result;

    /**
     * @param CmsPageCollection $result
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'result', newType: CmsPageCollection::class)]
    public function __construct(
        protected Request $request,
        /* protected CmsPageCollection $result, */
        EntityCollection $result,
        protected SalesChannelContext $salesChannelContext,
    ) {
        if (!$result instanceof CmsPageCollection) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing a plain EntityCollection as $result is deprecated, pass a CmsPageCollection instead.'
            );
        }

        $this->result = $result;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return CmsPageCollection
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: CmsPageCollection::class)]
    public function getResult(): EntityCollection /* CmsPageCollection */
    {
        return $this->result;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
