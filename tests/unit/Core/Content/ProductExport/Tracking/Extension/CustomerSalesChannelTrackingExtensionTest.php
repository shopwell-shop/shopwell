<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ProductExport\Tracking\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Content\ProductExport\Tracking\Extension\CustomerSalesChannelTrackingExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CustomerSalesChannelTrackingExtension::class)]
class CustomerSalesChannelTrackingExtensionTest extends TestCase
{
    public function testGetEntityName(): void
    {
        $extension = new CustomerSalesChannelTrackingExtension();

        static::assertSame(CustomerDefinition::ENTITY_NAME, $extension->getEntityName());
    }

    public function testExtendFieldsAddsCascadeDeleteAssociation(): void
    {
        $extension = new CustomerSalesChannelTrackingExtension();
        $collection = new FieldCollection();

        $extension->extendFields($collection);

        static::assertCount(1, $collection);

        $field = $collection->first();
        static::assertInstanceOf(OneToOneAssociationField::class, $field);
        static::assertSame('salesChannelTracking', $field->getPropertyName());
        static::assertTrue($field->is(CascadeDelete::class));
    }
}
