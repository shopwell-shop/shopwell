<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Log\Package;

/**
 * The values are persisted on every wallet entry, so they are data and not an implementation detail:
 * a stored `top_up` that a later release looks up as `topUp` is a booking the administration can no
 * longer label. Renaming one of them is therefore a migration that also rewrites rows, and this test
 * is what fails first when somebody tries to do it as a plain rename.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTransactionTypes::class)]
class WalletTransactionTypesTest extends TestCase
{
    public function testTheTypesAreTheStringsThatAreAlreadyStored(): void
    {
        $types = [
            WalletTransactionTypes::TOP_UP,
            WalletTransactionTypes::CONSUME,
            WalletTransactionTypes::REFUND,
            WalletTransactionTypes::ADJUST,
        ];

        // spelled out rather than compared against the constants themselves: the point is the exact
        // strings sitting in customer_wallet_transaction.type, and a new type has to be added here
        // deliberately instead of slipping in unnoticed
        static::assertSame('top_up,consume,refund,adjust', implode(',', $types));
    }
}
