<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentAttemptEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentAttemptCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_attempt_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentAttemptEntity::class;
    }
}
