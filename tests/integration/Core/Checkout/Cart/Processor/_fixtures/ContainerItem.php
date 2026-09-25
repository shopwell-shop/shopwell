<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
#[Package('checkout')]
class ContainerItem extends LineItem
{
    /**
     * @param array<LineItem> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct(Uuid::randomHex(), LineItem::CONTAINER_LINE_ITEM);

        $this->children = new LineItemCollection($items);

        $this->removable = true;
        $this->good = true;
    }
}
