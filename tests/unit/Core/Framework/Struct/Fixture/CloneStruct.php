<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Struct\Fixture;

use Shopwell\Core\Framework\Struct\CloneTrait;

/**
 * @internal
 */
class CloneStruct
{
    use CloneTrait;

    /**
     * @var array<array-key, CloneStruct>
     */
    public array $arrayOfStructs;

    public CloneStructBackedEnum $backedEnum;

    public CloneStructUnitEnum $unitEnum;

    public CloneStruct $nestedStruct;
}
