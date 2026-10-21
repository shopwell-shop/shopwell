<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPage;

/**
 * `verified` is what the template switches on: a page that was never authorised must not look like a
 * guest without returns.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GuestOrderReturnPage::class)]
class GuestOrderReturnPageTest extends TestCase
{
    public function testAFreshPageIsAVerificationFormAndNothingElse(): void
    {
        $page = new GuestOrderReturnPage();

        static::assertFalse($page->isVerified());
        static::assertSame([], $page->getReturns());
        static::assertSame([], $page->getPositions());
        static::assertSame([], $page->getReasons());
        static::assertNull($page->getCurrent());
        static::assertNull($page->getOrderNumber());
    }

    public function testAVerifiedPageCarriesWhatTheTokenProved(): void
    {
        $page = (new GuestOrderReturnPage())->assign([
            'verified' => true,
            'orderNumber' => 'SW-10001',
            'returns' => [['id' => 'return-id']],
            'current' => ['id' => 'return-id'],
            'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 1]],
            'reasons' => [['id' => 'reason-id', 'label' => 'broken']],
        ]);

        static::assertTrue($page->isVerified());
        static::assertSame('SW-10001', $page->getOrderNumber());
        static::assertSame([['id' => 'return-id']], $page->getReturns());
        static::assertSame(['id' => 'return-id'], $page->getCurrent());
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 1]], $page->getPositions());
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $page->getReasons());
    }
}
