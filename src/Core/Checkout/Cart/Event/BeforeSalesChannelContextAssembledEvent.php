<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows the manipulation of the sales channel context options before it is assembled from the order
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BeforeSalesChannelContextAssembledEvent extends Event
{
    /**
     * @param array<string, array<string, bool>|string|null> $options
     *
     * @internal
     */
    public function __construct(
        private readonly OrderEntity $order,
        private readonly Context $context,
        private array $options,
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, array<string, bool>|string|null>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, array<string, bool>|string|null> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
