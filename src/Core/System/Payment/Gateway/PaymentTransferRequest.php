<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Submits a transfer to its interface, the payout counterpart of the capture.
 *
 * Payum has no request for moving money out of the platform, so the platform names it here the same
 * way it names the {@see PaymentQuery} and the {@see PaymentClose}.
 *
 * The model is prepared by the driver - `PaymentGatewayModel::forTransfer()` - the way a payment
 * order rides its model: it is what the action records the request, the answer and the result on.
 * The request only carries the two entities on top of it, because the action cannot build the payout
 * request without the recipient: its identity type decides the shape of `payee_info`. The identity
 * value on the recipient is still encrypted - the action decrypts it with the credential cipher
 * right before it builds the request, so the plain text never lives longer than one call.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferRequest extends Generic
{
    /**
     * @param array<string, mixed> $channelParams the parameters of the very channel the submission is
     *                                            built with - alipay's `transfer_scene_name` and
     *                                            `transfer_scene_report_infos`, for instance. They
     *                                            belong to the call, never to the configuration.
     */
    public function __construct(
        PaymentGatewayModel $model,
        public readonly PaymentTransferEntity $transfer,
        public readonly PaymentTransferRecipientEntity $recipient,
        public readonly array $channelParams = [],
    ) {
        parent::__construct($model);
    }
}
