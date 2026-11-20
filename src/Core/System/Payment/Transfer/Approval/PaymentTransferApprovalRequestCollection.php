<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentTransferApprovalRequestEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentTransferApprovalRequestCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentTransferApprovalRequestEntity::class;
    }
}
