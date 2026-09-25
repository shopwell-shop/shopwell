<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class EntityWrittenEventSerializationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEventCanBeSerialized(): void
    {
        $container = $this->writeTestProduct();
        $event = $container->getEventByEntityName(ProductDefinition::ENTITY_NAME);

        $encoded = json_encode($event, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);

        $encoded = json_encode($container, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($encoded);
        static::assertJson($encoded);
    }

    private function writeTestProduct(): EntityWrittenContainerEvent
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        return $productRepository->create(
            [[
                'id' => Uuid::randomHex(),
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'wusel',
                'productNumber' => Uuid::randomHex(),
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
            ]],
            Context::createDefaultContext()
        );
    }
}
