<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Sitemap\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class SitemapQueryEvent extends Event implements GenericEvent, ShopwellSalesChannelEvent
{
    public function __construct(
        public readonly QueryBuilder $query,
        public readonly int $limit,
        public readonly ?int $offset,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
