<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront\Settlement;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a settlement attempt did.
 *
 * The settlement used to answer a boolean, which left every caller unable to tell a repeated payment
 * notification - harmless, the money is already there - from an amount the customer never agreed to.
 */
#[Package('checkout')]
enum PurchaseSettlementResult: string
{
    case Settled = 'settled';

    case AlreadySettled = 'already_settled';

    case AmountMismatch = 'amount_mismatch';

    case NotSettleable = 'not_settleable';

    case NotFound = 'not_found';

    public function isSettled(): bool
    {
        return $this === self::Settled;
    }
}
