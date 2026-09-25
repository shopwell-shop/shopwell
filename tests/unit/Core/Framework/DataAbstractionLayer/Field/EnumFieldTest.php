<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestIntegerEnum;
use Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\EnumField\TestStringEnum;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EnumField::class)]
#[Group('Field')]
#[Group('DAL')]
class EnumFieldTest extends TestCase
{
    public static function enumTypeProvider(): \Generator
    {
        yield 'Integer Enum detected as integer type' => [TestIntegerEnum::One, 'integer'];
        yield 'String Enum detected as string type' => [TestStringEnum::Regular, 'string'];
    }

    #[DataProvider('enumTypeProvider')]
    public function testEnumType(\BackedEnum $enumType, string $expectedType): void
    {
        $field = new EnumField(
            'name',
            'name',
            $enumType
        );
        static::assertSame($field->getType(), $expectedType);
        static::assertSame($field->getEnum(), $enumType);
    }

    public function testStorageName(): void
    {
        $field = new EnumField(
            'name',
            'name',
            TestStringEnum::Regular
        );
        static::assertSame('name', $field->getStorageName());
    }
}
