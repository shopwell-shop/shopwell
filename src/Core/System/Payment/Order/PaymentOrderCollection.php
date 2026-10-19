<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentOrderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentOrderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_order_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentOrderEntity::class;
    }
}
