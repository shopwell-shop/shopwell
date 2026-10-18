<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentSubscriptionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentSubscriptionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_subscription_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentSubscriptionEntity::class;
    }
}
