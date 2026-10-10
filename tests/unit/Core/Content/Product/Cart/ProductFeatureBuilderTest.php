<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopwell\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopwell\Core\Content\Property\PropertyGroupEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\CustomField\CustomFieldCollection;
use Shopwell\Core\System\CustomField\CustomFieldDefinition;
use Shopwell\Core\System\CustomField\CustomFieldEntity;
use Shopwell\Core\System\CustomField\CustomFieldTypes;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Unit\UnitEntity;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductFeatureBuilder::class)]
class ProductFeatureBuilderTest extends TestCase
{
    private const LANGUAGE_ID = 'language-id-123';

    private const CHILD_LANGUAGE_ID = 'child-language-id-123';

    private const LANGUAGE_CHAIN = [self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private const CHILD_LANGUAGE_CHAIN = [self::CHILD_LANGUAGE_ID, self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private const CURRENCY_ID = 'currency-id-123';

    private const REFERENCED_ENTITY_NAME = 'product';

    private const FIRST_ENTITY_ID = '0191c3a1c1a271b3a1a1c3a1c1a271b3';

    private const SECOND_ENTITY_ID = '0191c3a1c1a271b3a1a1c3a1c1a271b4';

    private ProductFeatureBuilder $productFeatureBuilder;

    /**
     * @var Stub&EntityRepository<CustomFieldCollection>
     */
    private Stub&EntityRepository $customFieldRepository;

    private Stub&LanguageLocaleCodeProvider $languageLocaleProvider;

    private Stub&DefinitionInstanceRegistry $definitionRegistry;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->customFieldRepository = static::createStub(EntityRepository::class);
        $this->languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->salesChannelContext = Generator::generateSalesChannelContext();

        $this->productFeatureBuilder = new ProductFeatureBuilder(
            $this->customFieldRepository,
            $this->languageLocaleProvider,
            $this->definitionRegistry
        );
    }

    public function testAddFeaturesIntoLineItems(): void
    {
        $productId = 'product-id-123';
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $product = new SalesChannelProductEntity();
        $product->setName('foo');
        $product->setId($productId);

        $group = new PropertyGroupEntity();
        $group->setTranslated(['name' => 'color']);
        $group->setId('group-1');

        $properties = new PropertyGroupOptionCollection();
        $property = new PropertyGroupOptionEntity();
        $property->setName('red');
        $property->setGroupId($group->getId());
        $property->setTranslated(['name' => 'red']);
        $property->setId('option-1');
        $property->setGroup($group);

        $properties->add($property);

        $unit = new UnitEntity();
        $unit->setTranslated(['name' => 'piece']);

        $product->setProperties($properties);
        $product->setUnit($unit);

        $referencePrice = new ReferencePrice(0.5, 1, 1, 'piece');

        $price = new CalculatedPrice(
            unitPrice: 1.0,
            totalPrice: 1.0,
            calculatedTaxes: new CalculatedTaxCollection(),
            taxRules: new TaxRuleCollection(),
            referencePrice: $referencePrice,
        );

        $lineItem->setPrice($price);

        $featureSet = new ProductFeatureSetEntity();
        $featureSet->setFeatures([
            ['name' => 'Color', 'id' => 'group-1', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY, 'position' => 1],
            ['name' => 'name', 'id' => 'feature-2', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE, 'position' => 2],
            ['name' => 'Feature 4', 'id' => 'feature-4', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE, 'position' => 4],
        ]);

        $product->setFeatureSet($featureSet);
        $data = new CartDataCollection();

        $data->set('product-' . $productId, $product);

        $this->productFeatureBuilder->add([$lineItem], $data, $this->salesChannelContext);

        static::assertNotEmpty($lineItem->getPayload()['features']);
        static::assertSame([
            [
                'label' => 'color',
                'value' => [
                    'option-1' => [
                        'id' => 'option-1',
                        'name' => 'red',
                        'mediaId' => null,
                        'colorHexCode' => null,
                    ],
                ],
                'type' => 'property',
            ],
            [
                'label' => 'name',
                'value' => 'foo',
                'type' => 'product',
            ],
            [
                'label' => 'referencePrice',
                'value' => [
                    'price' => 0.5,
                    'purchaseUnit' => 1.0,
                    'referenceUnit' => 1.0,
                    'unitName' => 'piece',
                ],
                'type' => 'referencePrice',
            ],
        ], $lineItem->getPayload()['features']);
    }

    public function testPrepareCollectsCustomFieldsFromTheTranslatedProductValue(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product-id-123');
        $product->setCustomFields(null);
        $product->setTranslated(['customFields' => ['custom_material' => 'wood']]);
        $product->setFeatureSet($this->createCustomFieldFeatureSet());

        $customField = $this->createCustomField(['en-GB' => 'Material']);

        $this->customFieldRepository->method('search')->willReturn(new EntitySearchResult(
            CustomFieldDefinition::ENTITY_NAME,
            1,
            new CustomFieldCollection([$customField]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $data = new CartDataCollection();
        $data->set('product-product-id-123', $product);

        $lineItem = new LineItem('product-id-123', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id-123');

        $this->productFeatureBuilder->prepare([$lineItem], $data, $this->salesChannelContext);

        static::assertSame($customField, $data->get('custom-field-custom_material'));
    }

    public function testCustomFieldLabelUsesTheLanguageOfTheContext(): void
    {
        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'zh-CN' => '材质',
        ]);

        static::assertSame([
            [
                'label' => '材质',
                'value' => [
                    'id' => 'custom-field-id',
                    'type' => CustomFieldTypes::TEXT,
                    'content' => 'wood',
                ],
                'type' => 'customField',
            ],
        ], $features);
    }

    /**
     * A child language inherits the label of its parent language before the system language is used.
     *
     * @param array<string, string> $labels
     */
    #[DataProvider('parentLanguageLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheParentLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels, self::CHILD_LANGUAGE_CHAIN);

        static::assertSame('材质', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('missingLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheSystemLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels);

        static::assertSame('Material', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('technicalNameLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheTechnicalName(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels);

        static::assertSame('custom_material', $features[0]['label']);
    }

    public function testSelectCustomFieldExposesTheLabelsOfTheSelectedOptions(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '材质'],
            content: ['oak', 'pine', 'unknown'],
            type: CustomFieldTypes::SELECT,
            config: [
                'options' => [
                    ['value' => 'oak', 'label' => ['en-GB' => 'Oak', 'zh-CN' => '橡木']],
                    ['value' => 'pine', 'label' => ['en-GB' => 'Pine']],
                ],
            ]
        );

        static::assertSame(['橡木', 'Pine', 'unknown'], $features[0]['value']['display']);
    }

    public function testSingleSelectCustomFieldExposesOneLabel(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '材质'],
            content: 'oak',
            type: CustomFieldTypes::SELECT,
            config: ['options' => [['value' => 'oak', 'label' => ['zh-CN' => '橡木']]]]
        );

        static::assertSame(['橡木'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldExposesTheNamesOfTheReferencedEntities(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '材质'],
            content: [self::FIRST_ENTITY_ID, self::SECOND_ENTITY_ID, Uuid::randomHex()],
            type: CustomFieldTypes::ENTITY,
            config: ['entity' => 'product'],
            referencedEntities: [
                self::FIRST_ENTITY_ID => ['name' => 'Oak'],
                self::SECOND_ENTITY_ID => ['name' => 'Pine'],
            ]
        );

        static::assertSame(['Oak', 'Pine'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldJoinsMultipleLabelProperties(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '材质'],
            content: self::FIRST_ENTITY_ID,
            type: CustomFieldTypes::SELECT,
            config: ['entity' => 'customer', 'labelProperty' => ['name', 'customerNumber']],
            referencedEntities: [self::FIRST_ENTITY_ID => ['name' => 'Max Mustermann', 'customerNumber' => '1337']]
        );

        static::assertSame(['Max Mustermann 1337'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldIsSkippedWhenTheEntityIsUnknown(): void
    {
        $this->definitionRegistry
            ->method('getRepository')
            ->willThrowException(new EntityRepositoryNotFoundException('lorem_ipsum'));

        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '材质'],
            content: self::FIRST_ENTITY_ID,
            type: CustomFieldTypes::ENTITY,
            config: ['entity' => 'lorem_ipsum']
        );

        static::assertSame([], $features);
    }

    public function testPriceCustomFieldIsResolvedForTheCurrencyOfTheContext(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '加价'],
            content: [
                ['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.9, 'linked' => true],
                ['currencyId' => self::CURRENCY_ID, 'net' => 20.0, 'gross' => 23.8, 'linked' => true],
            ],
            type: CustomFieldTypes::PRICE,
            context: $this->createSalesChannelContext(currencyId: self::CURRENCY_ID)
        );

        static::assertSame(23.8, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldUsesTheNetPriceForANetContext(): void
    {
        $context = $this->createSalesChannelContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '加价'],
            content: [['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.9, 'linked' => true]],
            type: CustomFieldTypes::PRICE,
            context: $context
        );

        static::assertSame(10.0, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldFallsBackToTheDefaultCurrencyTimesItsFactor(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '加价'],
            content: [['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.0, 'linked' => true]],
            type: CustomFieldTypes::PRICE,
            context: $this->createSalesChannelContext(currencyId: self::CURRENCY_ID, currencyFactor: 1.5)
        );

        static::assertSame(16.5, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldIsSkippedWithoutAMatchingCurrency(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['zh-CN' => '加价'],
            content: [],
            type: CustomFieldTypes::PRICE
        );

        static::assertSame([], $features);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function parentLanguageLabelProvider(): iterable
    {
        yield 'no label for the child language' => [['en-GB' => 'Material', 'zh-CN' => '材质']];
        yield 'empty label for the child language' => [['en-GB' => 'Material', 'zh-CN' => '材质', 'zh-TW' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingLabelProvider(): iterable
    {
        yield 'no label for the context language' => [['en-GB' => 'Material']];
        yield 'empty label for the context language' => [['en-GB' => 'Material', 'zh-CN' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function technicalNameLabelProvider(): iterable
    {
        yield 'no label at all' => [[]];
        yield 'no label for any language of the chain' => [['fr-FR' => 'Matériau']];
        yield 'empty system language label' => [['en-GB' => '']];
    }

    /**
     * Builds the features of a line item referencing a product with a single custom field feature.
     * The context defaults to the `zh-CN` language chain; the system language is `en-GB` throughout.
     *
     * @param array<string, string> $labels
     * @param non-empty-list<string> $languageIdChain
     * @param array<string, mixed> $config
     * @param array<string, array<string, string>> $referencedEntities
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildCustomFieldFeatures(
        array $labels,
        array $languageIdChain = self::LANGUAGE_CHAIN,
        mixed $content = 'wood',
        string $type = CustomFieldTypes::TEXT,
        array $config = [],
        array $referencedEntities = [],
        ?SalesChannelContext $context = null
    ): array {
        $this->languageLocaleProvider->method('getLocaleForLanguageId')->willReturnMap([
            [self::CHILD_LANGUAGE_ID, 'zh-TW'],
            [self::LANGUAGE_ID, 'zh-CN'],
            [Defaults::LANGUAGE_SYSTEM, 'en-GB'],
        ]);

        $this->definitionRegistry
            ->method('getRepository')
            ->willReturn($this->createEntityRepository($referencedEntities));

        $productId = 'product-id-123';
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);
        $product->setTranslated(['customFields' => ['custom_material' => $content]]);
        $product->setFeatureSet($this->createCustomFieldFeatureSet());

        $customField = $this->createCustomField($labels, $type, $config);

        $data = new CartDataCollection();
        $data->set('product-' . $productId, $product);
        $data->set('custom-field-custom_material', $customField);

        $context ??= $this->createSalesChannelContext(languageIdChain: $languageIdChain);

        $this->productFeatureBuilder->prepare([$lineItem], $data, $context);
        $this->productFeatureBuilder->add([$lineItem], $data, $context);

        return $lineItem->getPayload()['features'];
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private function createSalesChannelContext(
        array $languageIdChain = self::LANGUAGE_CHAIN,
        string $currencyId = Defaults::CURRENCY,
        float $currencyFactor = 1.0
    ): SalesChannelContext {
        return Generator::generateSalesChannelContext(
            baseContext: new Context(
                new SystemSource(),
                currencyId: $currencyId,
                languageIdChain: $languageIdChain,
                currencyFactor: $currencyFactor,
            ),
        );
    }

    private function createCustomFieldFeatureSet(): ProductFeatureSetEntity
    {
        $featureSet = new ProductFeatureSetEntity();
        $featureSet->setFeatures([
            ['name' => 'custom_material', 'id' => 'feature-1', 'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD, 'position' => 1],
        ]);

        return $featureSet;
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $config
     */
    private function createCustomField(array $labels, string $type = CustomFieldTypes::TEXT, array $config = []): CustomFieldEntity
    {
        $customField = new CustomFieldEntity();
        $customField->setId('custom-field-id');
        $customField->setName('custom_material');
        $customField->setType($type);
        $customField->setConfig(['label' => $labels] + $config);

        return $customField;
    }

    /**
     * @param array<string, array<string, string>> $entities
     *
     * @return Stub&EntityRepository<EntityCollection<Entity>>
     */
    private function createEntityRepository(array $entities): Stub&EntityRepository
    {
        $elements = [];

        foreach ($entities as $id => $values) {
            $entity = new ArrayEntity($values);
            $entity->setUniqueIdentifier($id);
            $entity->internalSetEntityData(self::REFERENCED_ENTITY_NAME, new FieldVisibility([]));

            $elements[] = $entity;
        }

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            self::REFERENCED_ENTITY_NAME,
            \count($elements),
            new EntityCollection($elements),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }
}
