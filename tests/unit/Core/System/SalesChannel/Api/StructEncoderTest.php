<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Api\ResponseFields;
use Shopwell\Core\System\SalesChannel\Api\StructEncoder;
use Shopwell\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StructEncoder::class)]
class StructEncoderTest extends TestCase
{
    /**
     * Regression test where the cheapest price and cheapest price container were exposed because the StructEncoder did not consider sales channel definitions
     */
    public function testCheapestPricesAreNotExposed(): void
    {
        $product = new SalesChannelProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCheapestPrice(
            (new CheapestPrice())->assign([
                'hasRange' => false,
                'variantId' => Uuid::randomHex(),
                'parentId' => Uuid::randomHex(),
                'ruleId' => Uuid::randomHex(),
                'purchase' => 1.0,
                'reference' => 1.0,
                'price' => new PriceCollection(),
            ])
        );

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertArrayNotHasKey('cheapestPrice', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertSame('test', $encoded['name']);
    }

    public function testNoneMappedFieldsAreNotExposed(): void
    {
        $product = new ExtendedProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertArrayNotHasKey('notExposed', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertSame('test', $encoded['name']);
    }

    public function testExtensionAreSupported(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $manufacturer = (new ProductManufacturerEntity())->assign(['name' => 'foo']);
        $product->addExtension('exposedExtension', $manufacturer);
        $product->addExtension('notExposedExtension', $manufacturer);
        $product->setName('test');

        $product->addExtension('foreignKeys', new ArrayStruct(['exposedFk' => 'exposed', 'notExposedFk' => 'not_exposed'], 'product'));
        $product->addExtension('search', new ArrayEntity(['score' => 2000]));

        $structEncoder = $this->createStructEncoder([ExtensionDefinition::class]);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertArrayHasKey('extensions', $encoded);
        static::assertArrayHasKey('exposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('search', $encoded['extensions']);
        static::assertArrayNotHasKey('notExposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('foreignKeys', $encoded['extensions']);

        static::assertArrayHasKey('score', $encoded['extensions']['search']);
        static::assertArrayHasKey('exposedFk', $encoded['extensions']['foreignKeys']);
        static::assertArrayNotHasKey('notExposedFk', $encoded['extensions']['foreignKeys']);
    }

    public function testPayloadProtection(): void
    {
        $cart = new Cart('test');

        $item = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE, 'test');

        $item->setPayload(['not_protected' => 'test', 'protected' => 'test'], ['not_protected' => false, 'protected' => true]);

        $cart->add($item);

        $structEncoder = $this->createStructEncoder();

        $encoded = $structEncoder->encode($cart, new ResponseFields());

        static::assertArrayHasKey('lineItems', $encoded);
        static::assertArrayHasKey(0, $encoded['lineItems']);
        static::assertArrayHasKey('payload', $encoded['lineItems'][0]);
        static::assertIsArray($encoded['lineItems'][0]['payload']);
        static::assertArrayHasKey('not_protected', $encoded['lineItems'][0]['payload']);
        static::assertArrayNotHasKey('protected', $encoded['lineItems'][0]['payload']);
    }

    public function testCustomFieldsAreExposed(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCustomFields(['visible_1' => 'test', 'visible_2' => 'test']);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        $expectedCustomFields = [
            'visible_1' => 'test',
            'visible_2' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testCustomFieldsWithPriceValueBeforeScalarValueAreEncoded(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setCustomFields([
            'custom_price_field' => new PriceCollection(),
            'custom_text_field' => 'Example text',
        ]);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertSame('Example text', $encoded['customFields']['custom_text_field']);
    }

    public function testCustomFieldsWithPriceValueAreFiltered(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setCustomFields([
            'custom_price_field' => new PriceCollection(),
            'visible_field' => 'Visible text',
            'blocked_field' => 'Blocked text',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'product',
                    'name' => 'blocked_field',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class], $connection);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertSame('Visible text', $encoded['customFields']['visible_field']);
        static::assertArrayNotHasKey('blocked_field', $encoded['customFields']);
    }

    public function testCustomFieldsFieldIsBlocked(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'product',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class], $connection);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testCustomFieldsFieldIsBlockedInNestedArray(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCustomFields(['visible' => 'test', 'blocked' => 'test']);
        $product->setTranslated(['customFields' => ['visible' => 'test', 'blocked' => 'test']]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'product',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class], $connection);

        $encoded = $structEncoder->encode($product, new ResponseFields());

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertEquals($expectedCustomFields, $encoded['customFields']);
        static::assertEquals($expectedCustomFields, $encoded['translated']['customFields']);
    }

    public function testResetReloadsBlockedCustomFields(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setName('test');
        $product->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'product',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class], $connection);

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);

        $structEncoder->reset();

        $encoded = $structEncoder->encode($product, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testResponseFieldsEncodeIncludesCorrectly(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setId('1');
        $product->setName('test');
        $product->setEan('ean123');

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $responseFields = new ResponseFields(['product' => ['id', 'name']]);

        $encoded = $structEncoder->encode($product, $responseFields);

        $expected = [
            'name' => 'test',
            'id' => '1',
            'apiAlias' => 'product',
        ];

        static::assertSame($expected, $encoded);
    }

    public function testResponseFieldsEncodeExcludesCorrectly(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setId('1');
        $product->setName('test');
        $product->setEan('ean123');

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $responseFields = new ResponseFields(excludes: ['product' => ['name']]);

        $encoded = $structEncoder->encode($product, $responseFields);

        static::assertArrayHasKey('id', $encoded);
        static::assertArrayHasKey('ean', $encoded);
        static::assertArrayNotHasKey('name', $encoded);
    }

    public function testResponseFieldsEncodeIncludesAndExcludesCorrectly(): void
    {
        $product = new ProductEntity();
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $product->setId('1');
        $product->setName('test');
        $product->setEan('ean123');

        $structEncoder = $this->createStructEncoder([SalesChannelProductDefinition::class]);

        $responseFields = new ResponseFields(['product' => ['id', 'name', 'ean']], ['product' => ['name']]);

        $encoded = $structEncoder->encode($product, $responseFields);

        $expected = [
            'ean' => 'ean123',
            'id' => '1',
            'apiAlias' => 'product',
        ];

        static::assertSame($expected, $encoded);
    }

    /**
     * @param array<int|string, class-string<EntityDefinition>|EntityDefinition> $definitions
     */
    private function createStructEncoder(array $definitions = [], ?Connection $connection = null): StructEncoder
    {
        $registry = new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $connection ??= static::createStub(Connection::class);

        return new StructEncoder($this->getChainRegistry($registry), $serializer, $connection);
    }

    private function getChainRegistry(StaticDefinitionInstanceRegistry $registry): DefinitionRegistryChain
    {
        $mock = static::createStub(ContainerInterface::class);

        return new DefinitionRegistryChain($registry, new SalesChannelDefinitionInstanceRegistry('', $mock, [], []));
    }
}

/**
 * @internal
 */
class ExtendedProductEntity extends ProductEntity
{
    public string $notExposed = 'test';
}

/**
 * @internal
 */
class ExtensionDefinition extends ProductDefinition
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new ManyToOneAssociationField('exposedExtension', 'my_extension_id', ProductManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new ManyToOneAssociationField('notExposedExtension', 'my_extension_id', ProductManufacturerDefinition::class))->addFlags(new Extension())
        );
        $fields->add(
            (new FkField('exposed_fk', 'exposedFk', ProductManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new FkField('not_exposed_fk', 'notExposedFk', ProductManufacturerDefinition::class))->addFlags(new Extension())
        );

        return $fields;
    }
}
