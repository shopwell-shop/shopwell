<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ProductExport\Tracking\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ProductExport\Tracking\Extension\SalesChannelProductExportTrackingExtension;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelProductExportTrackingExtension::class)]
class SalesChannelProductExportTrackingExtensionTest extends TestCase
{
    public function testGetEntityName(): void
    {
        $extension = new SalesChannelProductExportTrackingExtension();

        static::assertSame(SalesChannelDefinition::ENTITY_NAME, $extension->getEntityName());
    }

    public function testExtendFieldsAddsTrackingAssociations(): void
    {
        $extension = new SalesChannelProductExportTrackingExtension();
        $collection = new FieldCollection();

        $extension->extendFields($collection);

        static::assertCount(2, $collection);

        $orders = $collection->firstWhere(
            static fn (OneToManyAssociationField $field): bool => $field->getPropertyName() === 'salesChannelTrackingOrders',
        );
        static::assertInstanceOf(OneToManyAssociationField::class, $orders);
        static::assertSame(SalesChannelTrackingOrderDefinition::class, $orders->getReferenceClass());
        static::assertSame('sales_channel_id', $orders->getReferenceField());

        $customers = $collection->firstWhere(
            static fn (OneToManyAssociationField $field): bool => $field->getPropertyName() === 'salesChannelTrackingCustomers',
        );
        static::assertInstanceOf(OneToManyAssociationField::class, $customers);
        static::assertSame(SalesChannelTrackingCustomerDefinition::class, $customers->getReferenceClass());
        static::assertSame('sales_channel_id', $customers->getReferenceField());
    }
}
