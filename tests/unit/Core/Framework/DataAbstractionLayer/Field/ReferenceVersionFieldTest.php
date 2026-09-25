<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReferenceVersionField::class)]
class ReferenceVersionFieldTest extends TestCase
{
    public function testConstructorDerivesStorageAndPropertyNameFromTheDefinition(): void
    {
        $field = new ReferenceVersionField(ProductDefinition::class);

        static::assertSame('product_version_id', $field->getStorageName());
        static::assertSame('productVersionId', $field->getPropertyName());
        static::assertSame(ProductDefinition::class, $field->getVersionReferenceClass());
    }

    public function testConstructorUsesAnExplicitStorageName(): void
    {
        $field = new ReferenceVersionField(ProductDefinition::class, 'origin_version_id');

        static::assertSame('origin_version_id', $field->getStorageName());
        static::assertSame('originVersionId', $field->getPropertyName());
    }
}
