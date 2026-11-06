<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * How many of the customer's orders sit behind each account order filter.
 *
 * Every number on the account pages that counts orders comes from here, so the badge on the dashboard
 * and the tag on the order list can never disagree - they are the same query. Counting only reads the
 * total (`TOTAL_COUNT_MODE_EXACT` with a limit of one): a badge must not load every order of the
 * customer to find out how many there are.
 *
 * The filters are deliberately built on **to-one** associations only (`stateMachineState` and
 * `primaryOrderDelivery.stateMachineState`). Filtering a to-many association multiplies rows and would
 * report one order three times when it has three deliveries.
 *
 * No repository is touched here: the counts are read through the order route, exactly like the order
 * list next to them.
 *
 * @final
 */
#[Package('checkout')]
class AccountOrderStateCounter
{
    /**
     * The tag the order list starts on - no filter at all.
     */
    final public const FILTER_ALL = 'all';

    /**
     * Every tag of the order list and the filter it stands for.
     *
     * 待发货 / 待收货 are delivery states, not order states: an order that is `in_progress` can be
     * waiting to ship or already on its way, and only the delivery tells the two apart.
     */
    final public const FILTERS = [
        'pending_payment' => ['stateMachineState.technicalName', OrderStates::STATE_OPEN],
        'pending_shipment' => ['primaryOrderDelivery.stateMachineState.technicalName', 'open'],
        'pending_delivery' => ['primaryOrderDelivery.stateMachineState.technicalName', 'shipped'],
        'completed' => ['stateMachineState.technicalName', OrderStates::STATE_COMPLETED],
        'cancelled' => ['stateMachineState.technicalName', OrderStates::STATE_CANCELLED],
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractOrderRoute $orderRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The filter the request asks for, or `all` when it asks for nothing this shop knows.
     *
     * An unknown value falls back to the unfiltered list instead of erroring: a stale bookmark or a
     * hand-edited URL should show the orders, not a 400.
     */
    public function resolveFilter(?string $requested): string
    {
        if ($requested === null || $requested === '') {
            return self::FILTER_ALL;
        }

        return \array_key_exists($requested, self::FILTERS) ? $requested : self::FILTER_ALL;
    }

    /**
     * The filter to add to the order list criteria, `null` for the unfiltered list.
     */
    public function criteriaFilter(string $filter): ?EqualsFilter
    {
        if (!\array_key_exists($filter, self::FILTERS)) {
            return null;
        }

        [$field, $value] = self::FILTERS[$filter];

        return new EqualsFilter($field, $value);
    }

    /**
     * All tag counts, keyed by filter, plus the unfiltered total under {@see self::FILTER_ALL}.
     *
     * @return array<string, int>
     */
    public function counts(Request $request, SalesChannelContext $context): array
    {
        $counts = [self::FILTER_ALL => $this->countByFilter($request, $context, self::FILTER_ALL)];

        foreach (array_keys(self::FILTERS) as $filter) {
            $counts[$filter] = $this->countByFilter($request, $context, $filter);
        }

        return $counts;
    }

    /**
     * How many orders sit in one order state - the dashboard's "waiting for payment" style numbers.
     */
    public function countInOrderState(Request $request, SalesChannelContext $context, string $state): int
    {
        return $this->count(new EqualsFilter('stateMachineState.technicalName', $state), $request, $context);
    }

    public function countByFilter(Request $request, SalesChannelContext $context, string $filter): int
    {
        return $this->count($this->criteriaFilter($filter), $request, $context);
    }

    private function count(?EqualsFilter $filter, Request $request, SalesChannelContext $context): int
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if ($filter !== null) {
            $criteria->addFilter($filter);
        }

        $apiRequest = $request->duplicate();

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)
            ->getOrders()
            ->getTotal();
    }
}
