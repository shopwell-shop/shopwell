<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class SalesChannelContextCreatedEvent extends Event implements ShopwellSalesChannelEvent
{
    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $usedToken,
        private readonly array $session = []
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getUsedToken(): string
    {
        return $this->usedToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
