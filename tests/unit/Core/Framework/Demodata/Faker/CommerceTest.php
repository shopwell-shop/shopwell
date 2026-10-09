<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Demodata\Faker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Demodata\Faker\Commerce;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Commerce::class)]
class CommerceTest extends TestCase
{
    public function testCustomFieldSetReplacesSpacesWithUnderscores(): void
    {
        // pins the adjective list to a single entry with spaces, the list is read through late static binding
        $commerce = new class(Factory::create()) extends Commerce {
            // @phpstan-ignore shopwell.propertyNativeType (redeclares an untyped parent property, PHP forbids adding a type)
            protected static $productName = ['adjective' => ['Test Product Name']];
        };

        $setName = $commerce->customFieldSet();

        static::assertStringNotContainsString(' ', $setName);
        static::assertStringStartsWith('Test_Product_Name_', $setName);
    }
}
