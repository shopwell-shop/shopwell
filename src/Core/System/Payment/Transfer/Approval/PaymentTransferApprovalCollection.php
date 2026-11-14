<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<PaymentTransferApprovalEntity>
 */
#[Package('framework')]
class PaymentTransferApprovalCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentTransferApprovalEntity::class;
    }
}
