<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPage;

/**
 * The three screens share one page type, so what a template may read has to be defined for the case
 * where the current screen filled nothing in: an empty list is a list without returns, not a null.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AccountOrderReturnPage::class)]
class AccountOrderReturnPageTest extends TestCase
{
    public function testAFreshPageHasNothingToShow(): void
    {
        $page = new AccountOrderReturnPage();

        static::assertSame([], $page->getReturns());
        static::assertSame([], $page->getPositions());
        static::assertSame([], $page->getReasons());
        static::assertNull($page->getCurrent());
        static::assertNull($page->getOrderNumber());
    }

    public function testThePartsOfTheCurrentScreenAreReadable(): void
    {
        $page = (new AccountOrderReturnPage())->assign([
            'returns' => [['id' => 'return-id']],
            'current' => ['id' => 'return-id'],
            'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 1]],
            'reasons' => [['id' => 'reason-id', 'label' => 'broken']],
            'orderNumber' => 'SW-10001',
        ]);

        static::assertSame([['id' => 'return-id']], $page->getReturns());
        static::assertSame(['id' => 'return-id'], $page->getCurrent());
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 1]], $page->getPositions());
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $page->getReasons());
        static::assertSame('SW-10001', $page->getOrderNumber());
    }

    public function testTheSettersReplaceWhatTheScreenBeforeLeftBehind(): void
    {
        $page = new AccountOrderReturnPage();
        $page->setReturns([['id' => 'return-id']]);
        $page->setCurrent(['id' => 'return-id']);

        static::assertSame([['id' => 'return-id']], $page->getReturns());
        static::assertSame(['id' => 'return-id'], $page->getCurrent());
    }
}
