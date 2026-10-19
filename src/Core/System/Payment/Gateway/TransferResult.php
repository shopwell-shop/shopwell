<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel answered when a transfer was submitted.
 *
 * This is the raw answer narrowed to the shapes the interfaces actually produce - converting it into
 * a state of the transfer is the job of {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferService},
 * not of this value object: the same answer means different things depending on the origin state
 * (stripe reports a payout as `paid` and may still fail it five business days later), and a value
 * object has no business state to decide that.
 *
 * `providerStageRef` only exists for stripe, which moves the money in two steps and needs both
 * references to query what happened. `confirmPayload` only exists for wechat: the one time
 * `package_info` the shopper has to accept the transfer with - losing it means the transfer can only
 * be canceled and sent again.
 *
 * @internal
 */
#[Package('framework')]
final readonly class TransferResult
{
    /**
     * The interface took the request and will move the money by itself.
     */
    public const STATE_ACCEPTED = 'accepted';

    /**
     * Wechat only: the shopper has to accept the transfer in their app.
     */
    public const STATE_AWAITING_CONFIRM = 'awaiting_confirm';

    public const STATE_PROCESSING = 'processing';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED = 'failed';

    /**
     * The money reached the recipient and came back - alipay refunds a transfer to a bank card when
     * the bank refuses the account (`status=REFUND`), paypal returns a payout nobody claimed. Like
     * `failed` this is a terminal answer the platform can only learn after a `succeeded`.
     */
    public const STATE_RETURNED = 'returned';

    /**
     * The interface answered something that is no answer - neither success nor failure may be booked
     * from it, only a query of the channel can decide.
     */
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param string|null $providerRef the object every later query is addressed to; a rejection that
     *                                 never opened an object at the interface has none, the failure
     *                                 code is then the only evidence
     * @param string|null $providerStageRef only stripe: the `tr_` of the transfer into the connected account
     * @param string|null $confirmPayload only wechat: the `package_info` for the shopper to accept
     */
    public function __construct(
        public string $state,
        public ?string $providerRef = null,
        public ?string $providerStageRef = null,
        public ?string $confirmPayload = null,
        public ?string $failureCode = null,
    ) {
    }
}
