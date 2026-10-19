<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentWebhookDeliveryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentWebhookDeliveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_webhook_delivery_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentWebhookDeliveryEntity::class;
    }
}
