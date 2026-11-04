<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderStateCounter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderStateCounter::class)]
class AccountOrderStateCounterTest extends TestCase
{
    public function testEveryFilterHasALabelAndAFilter(): void
    {
        // five tags next to "all"; the order list owns six in total
        static::assertCount(5, AccountOrderStateCounter::FILTERS);
        static::assertSame(
            ['pending_payment', 'pending_shipment', 'pending_delivery', 'completed', 'cancelled'],
            array_keys(AccountOrderStateCounter::FILTERS)
        );
    }

    public function testResolveFilterKeepsAKnownFilter(): void
    {
        $counter = new AccountOrderStateCounter(static::createStub(OrderRoute::class), new CollectingEventDispatcher());

        static::assertSame('pending_payment', $counter->resolveFilter('pending_payment'));
    }

    #[DataProvider('unknownFilters')]
    public function testResolveFilterFallsBackToAllForAnythingUnknown(?string $requested): void
    {
        $counter = new AccountOrderStateCounter(static::createStub(OrderRoute::class), new CollectingEventDispatcher());

        // a stale bookmark must show the orders, not an error
        static::assertSame(AccountOrderStateCounter::FILTER_ALL, $counter->resolveFilter($requested));
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function unknownFilters(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'not a filter' => ['refunded'];
        yield 'case does not match' => ['PENDING_PAYMENT'];
        yield 'injection attempt' => ['open\' OR 1=1'];
    }

    public function testCriteriaFilterIsNullForTheUnfilteredList(): void
    {
        $counter = new AccountOrderStateCounter(static::createStub(OrderRoute::class), new CollectingEventDispatcher());

        static::assertNull($counter->criteriaFilter(AccountOrderStateCounter::FILTER_ALL));
        static::assertNull($counter->criteriaFilter('refunded'));
    }

    public function testCriteriaFilterMapsTheTagToTheFieldThatOwnsTheState(): void
    {
        $counter = new AccountOrderStateCounter(static::createStub(OrderRoute::class), new CollectingEventDispatcher());

        $payment = $counter->criteriaFilter('pending_payment');
        static::assertNotNull($payment);
        static::assertSame('stateMachineState.technicalName', $payment->getField());
        static::assertSame('open', $payment->getValue());

        // 待发货 is a delivery state: on the order itself both are `in_progress`
        $shipment = $counter->criteriaFilter('pending_shipment');
        static::assertNotNull($shipment);
        static::assertSame('primaryOrderDelivery.stateMachineState.technicalName', $shipment->getField());
        static::assertSame('open', $shipment->getValue());
    }

    public function testCountsReadTheTotalOfEveryFilterPlusTheUnfilteredOne(): void
    {
        $orderRoute = $this->orderRouteAnswering([3, 1, 1, 1, 22, 1]);
        $eventDispatcher = new CollectingEventDispatcher();

        $counter = new AccountOrderStateCounter($orderRoute, $eventDispatcher);

        $counts = $counter->counts(new Request(), static::createStub(SalesChannelContext::class));

        static::assertSame([
            'all' => 3,
            'pending_payment' => 1,
            'pending_shipment' => 1,
            'pending_delivery' => 1,
            'completed' => 22,
            'cancelled' => 1,
        ], $counts);

        // one route call per tag plus the total, each announced so a plugin can adjust it
        $events = array_filter(
            $eventDispatcher->getEvents(),
            static fn (object $event): bool => $event instanceof OrderRouteRequestEvent
        );
        static::assertCount(6, $events);
    }

    public function testOnlyTheTotalIsCountedNeverTheEntities(): void
    {
        $captured = [];
        $orderRoute = static::createStub(OrderRoute::class);
        $orderRoute->method('load')->willReturnCallback(function (Request $request, SalesChannelContext $context, Criteria $criteria) use (&$captured) {
            $captured[] = $criteria;

            return $this->response(0);
        });

        $counter = new AccountOrderStateCounter($orderRoute, new CollectingEventDispatcher());
        $counter->countByFilter(new Request(), static::createStub(SalesChannelContext::class), 'pending_payment');

        static::assertCount(1, $captured);
        static::assertSame(1, $captured[0]->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $captured[0]->getTotalCountMode());
    }

    public function testCountInOrderStateUsesThePlainOrderState(): void
    {
        $orderRoute = $this->orderRouteAnswering([4]);
        $counter = new AccountOrderStateCounter($orderRoute, new CollectingEventDispatcher());

        static::assertSame(4, $counter->countInOrderState(new Request(), static::createStub(SalesChannelContext::class), 'in_progress'));
    }

    /**
     * A route answering "total = n" for the n-th call; the entities stay empty because a count never
     * looks at them.
     *
     * @param list<int> $totals
     */
    private function orderRouteAnswering(array $totals): OrderRoute
    {
        $orderRoute = static::createStub(OrderRoute::class);
        $orderRoute->method('load')->willReturnCallback(function () use (&$totals) {
            static $call = 0;
            $total = $totals[$call] ?? 0;
            ++$call;

            return $this->response($total);
        });

        return $orderRoute;
    }

    private function response(int $total): OrderRouteResponse
    {
        return new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                $total,
                new OrderCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
    }
}
