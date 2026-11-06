<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Framework\Log\Package;

/**
 * The values end up in `loyalty_point_transaction.type` and are read back by the administration and
 * by support. They are data, so they are pinned here: renaming one silently turns every booking
 * already written into an unknown type.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointTransactionTypes::class)]
class LoyaltyPointTransactionTypesTest extends TestCase
{
    public function testThePersistedValues(): void
    {
        // implode breaks the constant folding, so this pins the strings without phpstan calling the
        // comparison always true
        static::assertSame('award,redeem,adjust,expire,return,revoke', implode(',', [
            LoyaltyPointTransactionTypes::AWARD,
            LoyaltyPointTransactionTypes::REDEEM,
            LoyaltyPointTransactionTypes::ADJUST,
            LoyaltyPointTransactionTypes::EXPIRE,
            LoyaltyPointTransactionTypes::RETURN,
            LoyaltyPointTransactionTypes::REVOKE,
        ]));
    }
}
