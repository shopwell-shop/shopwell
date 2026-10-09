<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api\Serializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserDefinition;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\SerializationFixture;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicStruct;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithExtension;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyExtension;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Shopwell\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;

/**
 * @internal
 */
#[Package('framework')]
class JsonApiEncoderTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(
            ProductDefinition::class,
            ProductExtension::class
        );

        $this->productRepository = static::getContainer()->get('product.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();

        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->beginTransaction();

        parent::tearDown();
    }

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty input null' => [null];
        yield 'empty input string' => ['string'];
        yield 'empty input 1' => [1];
        yield 'empty input false' => [false];
        yield 'empty input date time' => [new \DateTime()];
        yield 'empty input 1 point 1' => [1.1];
    }

    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        $this->expectExceptionObject(ApiException::unsupportedEncoderInput());

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encoder->encode(new Criteria(), static::getContainer()->get(ProductDefinition::class), $input, SerializationFixture::API_BASE_URL);
    }

    /**
     * @return iterable<string, array{class-string<EntityDefinition>, SerializationFixture}>
     */
    public static function complexStructsProvider(): iterable
    {
        yield 'media resource with basic struct is encoded' => [MediaDefinition::class, new TestBasicStruct()];
        yield 'user resource with to many relationships is encoded' => [UserDefinition::class, new TestBasicWithToManyRelationships()];
        yield 'media resource with to one relationship is encoded' => [MediaDefinition::class, new TestBasicWithToOneRelationship()];
        yield 'media folder resource with self reference collection is encoded' => [MediaFolderDefinition::class, new TestCollectionWithSelfReference()];
        yield 'media resource with collection to one relationship is encoded' => [MediaDefinition::class, new TestCollectionWithToOneRelationship()];
        yield 'rule resource filters internal fields' => [RuleDefinition::class, new TestInternalFieldsAreFiltered()];
        yield 'user resource keeps main resource out of included data' => [UserDefinition::class, new TestMainResourceShouldNotBeInIncluded()];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('complexStructsProvider')]
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        $definition = static::getContainer()->get($definitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR);

        // remove extensions from test
        $actual = $this->arrayRemove($actual, 'extensions');
        $actual['included'] = $this->removeIncludedExtensions($actual['included']);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), $actual);
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());
        $extendableDefinition->addExtension(new ScalarRuntimeExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithToManyExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithToManyExtension();

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        // check that empty "attributes" object is an object and not array: https://jsonapi.org/format/#document-resource-object-attributes
        static::assertStringNotContainsString('"attributes":[]', $actual);
        static::assertStringContainsString('"attributes":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testEncodeEntityWithToOneEntityExtension(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'Shopwell',
                    'link' => 'https://shopwell.cn',
                ],
                'toOne' => [
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('toOne');

        $productDefinition = static::getContainer()->get(ProductDefinition::class);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($productId);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $productDefinition, $product, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertIsArray($included['relationships']['toOne']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertSame('extended_product', $included['relationships']['toOne']['data']['type']);
            static::assertIsString($included['relationships']['toOne']['data']['id']);
            static::assertNotSame('', $included['relationships']['toOne']['data']['id']);
        }
    }

    public function testEncodeEntityWithToManyEntityExtension(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'Shopwell',
                    'link' => 'https://shopwell.cn',
                ],
                'oneToMany' => [
                    [
                        'name' => 'toMany01',
                    ],
                    [
                        'name' => 'toMany02',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('oneToMany');

        $productDefinition = static::getContainer()->get(ProductDefinition::class);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($productId);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $productDefinition, $product, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertIsArray($included['relationships']['oneToMany']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertCount(2, $included['relationships']['oneToMany']['data']);
            static::assertSame('extended_product', $included['relationships']['oneToMany']['data'][0]['type']);
            static::assertIsString($included['relationships']['oneToMany']['data'][0]['id']);
            static::assertNotSame('', $included['relationships']['oneToMany']['data'][0]['id']);
        }
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed>|null $output
     */
    #[DataProvider('customFieldsProvider')]
    public function testCustomFields(array $input, ?array $output): void
    {
        $encoder = static::getContainer()->get(JsonApiEncoder::class);

        $definition = new CustomFieldPlainTestDefinition();
        $definition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $struct = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct->setUniqueIdentifier(Uuid::randomHex());
        $struct->assign($input);

        $actual = json_decode((string) $encoder->encode(new Criteria(), $definition, $struct, SerializationFixture::API_BASE_URL), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($output, $actual['data']['attributes']['customFields']);
    }

    public static function customFieldsProvider(): \Generator
    {
        yield 'Custom field null' => [
            [
                'customFields' => null,
            ],
            null,
        ];

        yield 'Custom field with empty array' => [
            [
                'customFields' => [],
            ],
            [],
        ];

        yield 'Custom field with values' => [
            [
                'customFields' => ['bla'],
            ],
            ['bla'],
        ];
    }

    /**
     * @param array<mixed> $haystack
     *
     * @return array<mixed>
     */
    private function arrayRemove(array $haystack, string $keyToRemove): array
    {
        foreach ($haystack as $key => $value) {
            if (\is_array($value)) {
                $haystack[$key] = $this->arrayRemove($value, $keyToRemove);
            }

            if ($key === $keyToRemove) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    /**
     * @param array<array<mixed>> $array
     *
     * @return array<array<mixed>>
     */
    private function removeIncludedExtensions(array $array): array
    {
        $filtered = [];
        foreach ($array as $item) {
            if ($item['type'] !== 'extension') {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    private function assertValues(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $actual);

            if (\is_array($value)) {
                $this->assertValues($value, $actual[$key]);
            } else {
                static::assertSame($value, $actual[$key], 'Key: ' . $key);
            }
        }
    }
}
