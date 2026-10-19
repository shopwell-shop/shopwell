<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Security;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentGatewayTokenEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentGatewayTokenCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_gateway_token_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentGatewayTokenEntity::class;
    }
}
