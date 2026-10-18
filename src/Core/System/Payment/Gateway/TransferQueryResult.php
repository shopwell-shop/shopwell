<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel answered when a transfer was queried.
 *
 * The counterpart of {@see TransferResult}: a query resolves a submission whose outcome the platform
 * never learned, so it carries the same narrowed state plus the raw status string of the interface.
 * The raw status is kept on purpose - the four interfaces have four vocabularies (`SUCCESS`/`REFUND`
 * at alipay, `ACCEPTED`/`WAIT_USER_CONFIRM` at wechat, `paid`/`in_transit` at stripe, item states at
 * paypal), and a support case is debugged with the original string, not with the mapped state.
 *
 * The state constants are the ones of {@see TransferResult} - a query answer and a submission answer
 * are the same kind of answer.
 *
 * @internal
 */
#[Package('framework')]
final readonly class TransferQueryResult
{
    /**
     * @param string $state one of the TransferResult::STATE_* values
     * @param string $providerStatus the status the interface reported, unchanged
     * @param string|null $providerRef the object the interface handed out; a query that found
     *                                 nothing may have none
     */
    public function __construct(
        public string $state,
        public string $providerStatus,
        public ?string $providerRef = null,
        public ?string $providerStageRef = null,
        public ?string $confirmPayload = null,
        public ?string $failureCode = null,
    ) {
    }
}
