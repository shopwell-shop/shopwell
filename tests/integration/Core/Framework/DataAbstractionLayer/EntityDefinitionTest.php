<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopwell\Core\Content\Property\PropertyGroupDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopwell\Core\System\StateMachine\StateMachineDefinition;
use Shopwell\Core\System\StateMachine\StateMachineTranslationDefinition;

/**
 * @internal
 */
#[Package('framework')]
class EntityDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityDefinitionCompilation(): void
    {
        $definition = static::getContainer()->get(ProductDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        $productManufacturerVersionIdField = $definition->getFields()->get('productManufacturerVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $productManufacturerVersionIdField);
        static::assertSame('product_manufacturer_version_id', $productManufacturerVersionIdField->getStorageName());
        static::assertInstanceOf(ProductManufacturerDefinition::class, $productManufacturerVersionIdField->getVersionReferenceDefinition());
        static::assertSame(static::getContainer()->get(ProductManufacturerDefinition::class), $productManufacturerVersionIdField->getVersionReferenceDefinition());
    }

    public function testTranslationCompilation(): void
    {
        $definition = static::getContainer()->get(ProductTranslationDefinition::class);

        static::assertContainsOnlyInstancesOf(Field::class, $definition->getFields());
        $languageIdField = $definition->getFields()->get('languageId');
        static::assertInstanceOf(FkField::class, $languageIdField);
        static::assertSame('language_id', $languageIdField->getStorageName());
    }

    #[DataProvider('provideTranslatedDefinitions')]
    public function testTranslationsOnDefinitions(string $baseDefinitionClass, string $translationDefinitionClass): void
    {
        $baseDefinition = static::getContainer()->get($baseDefinitionClass);
        $translationDefinition = static::getContainer()->get($translationDefinitionClass);

        static::assertInstanceOf(EntityDefinition::class, $baseDefinition);
        static::assertInstanceOf(EntityTranslationDefinition::class, $translationDefinition);
        static::assertSame($translationDefinition, $baseDefinition->getTranslationDefinition());
        static::assertInstanceOf(JsonField::class, $baseDefinition->getFields()->get('translated'));
        static::assertSame($baseDefinition->getClass(), $translationDefinition->getParentDefinition()->getClass());
        static::assertSame($baseDefinition, $translationDefinition->getParentDefinition());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function provideTranslatedDefinitions(): array
    {
        return [
            [CmsPageDefinition::class, CmsPageTranslationDefinition::class],
            [CmsSlotDefinition::class, CmsSlotTranslationDefinition::class],
            [PropertyGroupDefinition::class, PropertyGroupTranslationDefinition::class],
            [StateMachineDefinition::class, StateMachineTranslationDefinition::class],
            [StateMachineStateDefinition::class, StateMachineStateTranslationDefinition::class],
            [ProductDefinition::class, ProductTranslationDefinition::class],
            [PromotionDefinition::class, PromotionTranslationDefinition::class],
        ];
    }

    public function testGetFieldVisibility(): void
    {
        $definition = static::getContainer()->get(CustomerDefinition::class);

        $internalFields = [
            'password',
            'newsletterSalesChannelIds',
            'legacyPassword',
            'legacyEncoder',
        ];

        foreach ($internalFields as $field) {
            static::assertTrue($definition->getFieldVisibility()->isVisible($field));
            FieldVisibility::$isInTwigRenderingContext = true;
            static::assertFalse($definition->getFieldVisibility()->isVisible($field));
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }
}
