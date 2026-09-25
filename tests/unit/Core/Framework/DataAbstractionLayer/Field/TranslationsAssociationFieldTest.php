<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslationsAssociationField::class)]
class TranslationsAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheTranslationAssociation(): void
    {
        $field = new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id');

        static::assertSame('translations', $field->getPropertyName());
        static::assertSame(ProductTranslationDefinition::class, $field->getReferenceClass());
        static::assertSame('product_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(CascadeDelete::class));
        static::assertSame('language_id', $field->getLanguageField());
        static::assertSame(TranslationsAssociationField::PRIORITY, $field->getExtractPriority());
    }
}
