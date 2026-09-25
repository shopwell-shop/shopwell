<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItemFactoryHandler;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionLineItemFactory implements LineItemFactoryInterface
{
    public function supports(string $type): bool
    {
        return $type === LineItem::PROMOTION_LINE_ITEM_TYPE;
    }

    /**
     * @param array<string, string> $data
     */
    public function create(array $data, SalesChannelContext $context): LineItem
    {
        $code = mb_trim($data['referencedId']);
        $uniqueKey = 'promotion-' . $code;

        $item = new LineItem(Uuid::fromStringToHex($uniqueKey), LineItem::PROMOTION_LINE_ITEM_TYPE);
        $item->setLabel($uniqueKey);
        $item->setGood(false);

        // this is used to pass on the code for later usage
        $item->setReferencedId($code);

        // this is important to avoid any side effects when calculating the cart
        // a percentage of 0,00 will just do nothing
        $item->setPriceDefinition(new PercentagePriceDefinition(0));

        return $item;
    }

    /**
     * @param array<string, string> $data
     */
    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        throw CartException::lineItemTypeNotUpdatable($lineItem->getType());
    }
}
