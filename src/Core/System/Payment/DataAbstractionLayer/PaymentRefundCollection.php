<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentRefundEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentRefundCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_refund_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentRefundEntity::class;
    }
}
