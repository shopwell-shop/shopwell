<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BreadcrumbField::class)]
class BreadcrumbFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new BreadcrumbField();

        static::assertSame('breadcrumb', $field->getStorageName());
        static::assertSame('breadcrumb', $field->getPropertyName());
        static::assertSame([], $field->getPropertyMapping());
        static::assertNull($field->getDefault());
    }

    public function testConstructorPassesExplicitValuesToTheParent(): void
    {
        $field = new BreadcrumbField('path', 'pathBreadcrumb', [], ['home']);

        static::assertSame('path', $field->getStorageName());
        static::assertSame('pathBreadcrumb', $field->getPropertyName());
        static::assertSame(['home'], $field->getDefault());
    }
}
