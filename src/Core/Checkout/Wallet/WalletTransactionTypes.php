<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet;

use Shopwell\Core\Framework\Log\Package;

/**
 * The kinds of entry the wallet ledger knows.
 *
 * They are persisted as they are, so the values are part of the data and not free to rename: a stored
 * `top_up` that is looked up as `topUp` is a booking the administration cannot label any more.
 */
#[Package('checkout')]
final class WalletTransactionTypes
{
    public const TOP_UP = 'top_up';

    public const CONSUME = 'consume';

    public const REFUND = 'refund';

    public const ADJUST = 'adjust';
}
