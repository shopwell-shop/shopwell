<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
#[Package('checkout')]
class AccountOrderDetailPage extends Page
{
    protected OrderEntity $order;

    protected ?OrderLineItemCollection $lineItems = null;

    public function getOrder(): OrderEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->order;
    }

    public function setOrder(OrderEntity $order): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->order = $order;

        return $this;
    }

    public function getLineItems(): ?OrderLineItemCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->lineItems;
    }

    public function setLineItems(?OrderLineItemCollection $lineItems): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->lineItems = $lineItems;

        return $this;
    }
}
