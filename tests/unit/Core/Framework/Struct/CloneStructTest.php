<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\CloneTrait;
use Shopwell\Tests\Unit\Core\Framework\Struct\Fixture\CloneStruct;
use Shopwell\Tests\Unit\Core\Framework\Struct\Fixture\CloneStructBackedEnum;
use Shopwell\Tests\Unit\Core\Framework\Struct\Fixture\CloneStructUnitEnum;

/**
 * @internal
 */
#[Package('framework')]
#[CoversTrait(CloneTrait::class)]
class CloneStructTest extends TestCase
{
    public function testClone(): void
    {
        $nestedStruct = new CloneStruct();
        $nestedStruct->backedEnum = CloneStructBackedEnum::Case;
        $nestedStruct->unitEnum = CloneStructUnitEnum::Case;

        $original = new CloneStruct();
        $original->arrayOfStructs = [$nestedStruct];
        $original->backedEnum = CloneStructBackedEnum::Case;
        $original->nestedStruct = $nestedStruct;
        $original->unitEnum = CloneStructUnitEnum::Case;

        $clone = clone $original;

        static::assertEquals($original, $clone);
        static::assertNotSame($original, $clone);

        static::assertNotSame($original->arrayOfStructs[0], $clone->arrayOfStructs[0]);
        static::assertNotSame($original->nestedStruct, $clone->nestedStruct);
    }
}
