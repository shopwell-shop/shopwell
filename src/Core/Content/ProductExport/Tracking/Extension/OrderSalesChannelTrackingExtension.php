<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Tracking\Extension;

use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class OrderSalesChannelTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                'salesChannelTracking',
                'id',
                'order_id',
                SalesChannelTrackingOrderDefinition::class,
                false,
            ),
        );
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}
