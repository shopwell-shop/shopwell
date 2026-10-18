<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentPlanEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentPlanCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_plan_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentPlanEntity::class;
    }
}
