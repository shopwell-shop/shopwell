<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentMandateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentMandateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_mandate_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMandateEntity::class;
    }
}
