<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentMethodGatewayChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PaymentMethodGatewayChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_method_gateway_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodGatewayChannelEntity::class;
    }
}
