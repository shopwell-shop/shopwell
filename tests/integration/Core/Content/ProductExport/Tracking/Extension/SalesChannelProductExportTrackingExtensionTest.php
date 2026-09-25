<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\ProductExport\Tracking\Extension;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelProductExportTrackingExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSalesChannelDefinitionContainsTrackingAssociations(): void
    {
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->getByEntityName(SalesChannelDefinition::ENTITY_NAME);
        static::assertInstanceOf(SalesChannelDefinition::class, $definition);

        $fields = $definition->getFields();

        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('salesChannelTrackingOrders'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('salesChannelTrackingCustomers'));
    }
}
