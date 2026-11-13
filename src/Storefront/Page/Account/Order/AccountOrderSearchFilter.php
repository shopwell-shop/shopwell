<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * Shared by the paginated list and its state counts.
 *
 * @internal
 */
#[Package('checkout')]
final class AccountOrderSearchFilter
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function apply(Criteria $criteria, string $search, int $days): void
    {
        $search = mb_substr(trim($search), 0, 100);
        if ($search !== '') {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('orderNumber', $search),
                new ContainsFilter('lineItems.label', $search),
            ]));
        }

        if (\in_array($days, [7, 30, 90], true)) {
            $start = $this->clock->now()->setTime(0, 0)->modify('-' . ($days - 1) . ' days');
            $criteria->addFilter(new RangeFilter('orderDateTime', [
                RangeFilter::GTE => $start->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]));
        }
    }
}
