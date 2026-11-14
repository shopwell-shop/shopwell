<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalException extends PaymentException
{
    public static function invalidIds(): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'PAYMENT__TRANSFER_APPROVAL_IDS_INVALID', 'Expected a list of at most 100 transfer UUIDs.');
    }

    public static function blocked(string $reason): self
    {
        return new self(Response::HTTP_CONFLICT, 'PAYMENT__TRANSFER_APPROVAL_REQUIRED', 'The transfer approval is not executable: {{ reason }}.', ['reason' => $reason]);
    }
}
