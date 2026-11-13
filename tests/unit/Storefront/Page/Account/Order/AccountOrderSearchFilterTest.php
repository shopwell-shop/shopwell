<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Account\Order\AccountOrderSearchFilter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderSearchFilter::class)]
class AccountOrderSearchFilterTest extends TestCase
{
    public function testSearchMatchesOrderNumberOrPurchasedProductName(): void
    {
        $criteria = new Criteria();
        $filter = new AccountOrderSearchFilter(new MockClock('2026-11-12 10:00:00 UTC'));

        $filter->apply($criteria, '  陶瓷  ', 7);

        $filters = $criteria->getFilters();
        static::assertCount(2, $filters);
        static::assertInstanceOf(MultiFilter::class, $filters[0]);
        static::assertSame(MultiFilter::CONNECTION_OR, $filters[0]->getOperator());
        static::assertEquals([
            new ContainsFilter('orderNumber', '陶瓷'),
            new ContainsFilter('lineItems.label', '陶瓷'),
        ], $filters[0]->getQueries());
        static::assertInstanceOf(RangeFilter::class, $filters[1]);
        static::assertSame('2026-11-06 00:00:00.000', $filters[1]->getParameter(RangeFilter::GTE));
    }

    public function testUnknownPeriodAndBlankSearchDoNotHideOrders(): void
    {
        $criteria = new Criteria();
        $filter = new AccountOrderSearchFilter(new MockClock('2026-11-12'));

        $filter->apply($criteria, '  ', -1);

        static::assertSame([], $criteria->getFilters());
    }

    public function testSearchLengthIsLimitedWithoutBreakingChineseCharacters(): void
    {
        $criteria = new Criteria();
        $filter = new AccountOrderSearchFilter(new MockClock('2026-11-12'));
        $filter->apply($criteria, str_repeat('瓷', 200), 0);

        $search = $criteria->getFilters()[0];
        static::assertInstanceOf(MultiFilter::class, $search);
        $number = $search->getQueries()[0];
        static::assertInstanceOf(ContainsFilter::class, $number);
        static::assertSame(str_repeat('瓷', 100), $number->getValue());
    }
}
