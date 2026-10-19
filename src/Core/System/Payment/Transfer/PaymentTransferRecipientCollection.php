<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentTransferRecipientEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentTransferRecipientCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'payment_transfer_recipient_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentTransferRecipientEntity::class;
    }
}
