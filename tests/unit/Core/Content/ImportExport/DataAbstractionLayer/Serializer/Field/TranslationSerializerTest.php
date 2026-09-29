<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\TranslationsSerializer;
use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopwell\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(TranslationsSerializer::class)]
class TranslationSerializerTest extends TestCase
{
    public function testSerializationWithNullTranslations(): void
    {
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $config = $this->getConfig();

        $translations = \iterator_to_array($translationsSerializer->serialize($config, $this->getTranslationsAssociationField(), null));

        static::assertEmpty($translations);
    }

    public function testSerializationWithInvalidField(): void
    {
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $field = new BlobField('foo', 'bar');

        $this->expectExceptionObject(ImportExportException::invalidInstanceType('associationField', TranslationsAssociationField::class));

        \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $field, []));
    }

    public function testSerialization(): void
    {
        $languageRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'language',
                1,
                new LanguageCollection([
                    (new LanguageEntity())->assign([
                        'id' => Defaults::LANGUAGE_SYSTEM,
                        'translationCode' => (new LocaleEntity())->assign([
                            'code' => 'en-GB',
                        ]),
                    ]),
                ]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
        ]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = [
            Defaults::LANGUAGE_SYSTEM => [
                'name' => 'foo',
            ],
            'zh-CN' => [
                'name' => 'bar',
            ],
        ];

        $translationsSerialized = \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $this->getTranslationsAssociationField(), $translations));

        static::assertSame([
            'translations' => [
                'en-GB' => [
                    'name' => 'foo',
                ],
                'DEFAULT' => [
                    'name' => 'foo',
                ],
                'zh-CN' => [
                    'name' => 'bar',
                ],
            ],
        ], $translationsSerialized);
    }

    public function testDeserializationWithEmptyTranslations(): void
    {
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = $translationsSerializer->deserialize($this->getConfig(), $this->getTranslationsAssociationField(), []);

        static::assertNull($translations);
    }

    public function testDeserializationWithInvalidField(): void
    {
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $field = new BlobField('foo', 'bar');

        $this->expectExceptionObject(ImportExportException::invalidInstanceType('associationField', '*ToOneField'));

        $translationsSerializer->deserialize($this->getConfig(), $field, []);
    }

    public function testDeserialization(): void
    {
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = [
            'DEFAULT' => [
                'name' => 'foo',
            ],
            'zh-CN' => [
                'name' => 'bar',
            ],
            'en-GB' => [],
        ];

        $translationsSerialized = $translationsSerializer->deserialize($this->getConfig(), $this->getTranslationsAssociationField(), $translations);

        static::assertSame([
            'zh-CN' => [
                'name' => 'bar',
            ],
            Defaults::LANGUAGE_SYSTEM => [
                'name' => 'foo',
            ],
        ], $translationsSerialized);
    }

    public function testSupports(): void
    {
        $languageRepository = new StaticEntityRepository([]);
        $translationsSerializer = new TranslationsSerializer($languageRepository);

        static::assertTrue($translationsSerializer->supports($this->getTranslationsAssociationField()));
    }

    /**
     * @param StaticEntityRepository<LanguageCollection> $languageRepository
     */
    private function getTranslationSerializer(StaticEntityRepository $languageRepository): TranslationsSerializer
    {
        $translationsSerializer = new TranslationsSerializer(
            $languageRepository,
        );

        $entitySerializer = new EntitySerializer();
        $fieldSerializer = new FieldSerializer();

        $serializerRegistry = new SerializerRegistry([$entitySerializer], [$fieldSerializer]);
        $entitySerializer->setRegistry($serializerRegistry);
        $fieldSerializer->setRegistry($serializerRegistry);
        $translationsSerializer->setRegistry($serializerRegistry);

        return $translationsSerializer;
    }

    private function getConfig(): Config
    {
        return new Config(
            new MappingCollection(),
            [],
            new UpdateByCollection()
        );
    }

    private function getTranslationsAssociationField(): TranslationsAssociationField
    {
        $productTranslationDefinition = new ProductTranslationDefinition();
        $productDefinition = new ProductDefinition();

        $container = new Container();
        $container->set(ProductTranslationDefinition::class, $productTranslationDefinition);
        $container->set(ProductDefinition::class, $productDefinition);
        $productTranslationDefinition->compile(new DefinitionInstanceRegistry($container, [], []));

        $translationAssociationField = new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id');
        $translationAssociationField->compile(new DefinitionInstanceRegistry($container, [], []));

        return $translationAssociationField;
    }
}
