<?php declare(strict_types=1);

namespace Shopwell\Storefront\Checkout\Order;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * Order line items keep referencing products that were deactivated, hidden or deleted, so the order alone cannot
 * tell whether one is still shown in this sales channel. Answers that as the `productAvailability` line item
 * extension, for the pages that render order line items.
 */
#[Package('checkout')]
class OrderProductAvailabilityResolver
{
    public const LINE_ITEM_EXTENSION = 'productAvailability';

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory
    ) {
    }

    /**
     * @param iterable<OrderEntity> $orders
     */
    public function addAvailability(iterable $orders, SalesChannelContext $context): void
    {
        $deleted = [];
        $byProductId = [];

        foreach ($orders as $order) {
            foreach ($order->getLineItems() ?? [] as $lineItem) {
                if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                    continue;
                }

                $productId = $lineItem->getProductId();

                if ($productId === null) {
                    $deleted[] = $lineItem;

                    continue;
                }

                $byProductId[$productId][] = $lineItem;
            }
        }

        // an empty id list leaves the criteria unfiltered and unlimited, which would read the whole catalogue
        $visible = $byProductId === [] ? [] : $this->getVisibleProductIds(array_keys($byProductId), $context);

        foreach ($deleted as $lineItem) {
            $lineItem->addExtension(self::LINE_ITEM_EXTENSION, new ArrayStruct(['visible' => false]));
        }

        foreach ($byProductId as $productId => $lineItems) {
            foreach ($lineItems as $lineItem) {
                $lineItem->addExtension(self::LINE_ITEM_EXTENSION, new ArrayStruct([
                    'visible' => isset($visible[$productId]),
                ]));
            }
        }
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, int>
     */
    private function getVisibleProductIds(array $productIds, SalesChannelContext $context): array
    {
        $criteria = new Criteria($productIds);
        $criteria->setTitle('order-line-item::product-availability');

        // mirrors ProductDetailRoute, so the answer matches whether the detail page resolves
        if ($this->systemConfigService->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $context->getSalesChannelId())) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
        }

        // only a yes/no is needed, and any product read runs the price calculation over every id, partial or not
        return array_flip($this->productRepository->searchIds($criteria, $context)->getIds());
    }
}
