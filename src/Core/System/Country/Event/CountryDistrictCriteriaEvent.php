<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryDistrictCriteriaEvent extends Event implements ShopwellSalesChannelEvent
{
    public function __construct(
        private readonly string $countryCityId,
        private readonly Request $request,
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getCountryCityId(): string
    {
        return $this->countryCityId;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
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
