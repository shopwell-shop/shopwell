<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Tracking\Extension;

use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class SalesChannelProductExportTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField(
                'salesChannelTrackingOrders',
                SalesChannelTrackingOrderDefinition::class,
                'sales_channel_id',
                'id',
            ),
        );

        $collection->add(
            new OneToManyAssociationField(
                'salesChannelTrackingCustomers',
                SalesChannelTrackingCustomerDefinition::class,
                'sales_channel_id',
                'id',
            ),
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}
