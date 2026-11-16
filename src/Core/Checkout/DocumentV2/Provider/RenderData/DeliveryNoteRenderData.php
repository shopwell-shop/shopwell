<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopwell\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * Render payload for the delivery note. Delivery note ships HTML + PDF only; the HTML template
 * walks the raw `OrderEntity` and reads delivery-note fields from the `custom` bag
 * (`deliveryNoteNumber`, `deliveryDate`, `deliveryNoteDate`). Cross-cutting data (config, company,
 * display, document identity) lives in {@see DocumentMetaRenderData}.
 * For a note of a single delivery, `orderDelivery` and `deliveryLineItemQuantities` narrow it to that delivery.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class DeliveryNoteRenderData extends AbstractRenderData
{
    /**
     * @param array<string, mixed> $custom
     * @param array<string, int>|null $deliveryLineItemQuantities
     */
    public function __construct(
        /**
         * @deprecated tag:v6.8.0 - feeds the legacy flat `config.custom.*` template contract
         */
        public array $custom = [],
        public ?OrderDeliveryEntity $orderDelivery = null,
        public ?array $deliveryLineItemQuantities = null,
    ) {
    }
}
