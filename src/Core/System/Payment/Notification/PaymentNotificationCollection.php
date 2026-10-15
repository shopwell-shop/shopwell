<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentNotificationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentNotificationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_notification_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentNotificationEntity::class;
    }
}
