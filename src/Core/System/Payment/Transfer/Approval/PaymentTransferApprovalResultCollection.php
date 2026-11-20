<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentTransferApprovalResultEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentTransferApprovalResultCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentTransferApprovalResultEntity::class;
    }
}
