<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CalculatedPriceFieldTestDefinition;

/**
 * @internal
 */
#[Package('framework')]
class CalculatedPriceFieldTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    public function testListPrice(): void
    {
        $definition = $this->registerDefinition(CalculatedPriceFieldTestDefinition::class);
        $connection = static::getContainer()->get(Connection::class);

        $connection->rollBack();
        $connection->executeStatement(CalculatedPriceFieldTestDefinition::getCreateTable());
        $connection->beginTransaction();

        $ids = new IdsCollection();

        $data = [
            'id' => $ids->create('entity'),
            'price' => new CalculatedPrice(
                100,
                100,
                new CalculatedTaxCollection([]),
                new TaxRuleCollection([]),
                1,
                null,
                ListPrice::createFromUnitPrice(100, 200)
            ),
        ];

        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        static::getContainer()->get(EntityWriter::class)
            ->insert($definition, [$data], $writeContext);

        $entity = static::getContainer()->get(EntityReaderInterface::class)
            ->read($definition, new Criteria([$ids->get('entity')]), Context::createDefaultContext())
            ->get($ids->get('entity'));
        static::assertInstanceOf(ArrayEntity::class, $entity);

        $price = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertInstanceOf(ListPrice::class, $price->getListPrice());
        static::assertSame(200.0, $price->getListPrice()->getPrice());
        static::assertSame(-100.0, $price->getListPrice()->getDiscount());
        static::assertSame(50.0, $price->getListPrice()->getPercentage());
    }
}
