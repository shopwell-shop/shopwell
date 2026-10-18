<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Asks the interface what happened to a transfer, the payout counterpart of the {@see PaymentQuery}.
 *
 * Alipay answers "the call succeeded" with `code=10000` while the transfer itself may still be
 * dealing or even refunded later, so every outcome the platform never learned synchronously is
 * resolved here - and a retry before the query is a second payout, never a retry.
 *
 * The model is prepared by the driver - `PaymentGatewayModel::forTransfer()` - the same way the
 * submission carries it. The recipient rides along although a query only names the transfer: queried
 * by the out business number, alipay demands the very `product_code` and `biz_scene` the submission
 * was built with, and those follow the identity type of the recipient.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferQuery extends Generic
{
    public function __construct(
        PaymentGatewayModel $model,
        public readonly PaymentTransferEntity $transfer,
        public readonly PaymentTransferRecipientEntity $recipient,
    ) {
        parent::__construct($model);
    }
}
