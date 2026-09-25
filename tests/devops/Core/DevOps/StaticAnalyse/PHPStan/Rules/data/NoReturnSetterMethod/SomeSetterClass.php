<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

use Shopwell\Core\Content\Product\ProductEntity;

/**
 * @internal
 */
final class SomeSetterClass
{
    public function setName(string $name)
    {
        return 100;
    }

    public function setWithReturnType(string $name): object
    {
        return new \stdClass();
    }

    public function setWithObjectReturnType(string $name): ProductEntity
    {
        return new ProductEntity();
    }
}
