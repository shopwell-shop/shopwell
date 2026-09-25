<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Struct\Fixture;

use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<AssignTestStruct>
 */
class AssignTestCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return AssignTestStruct::class;
    }
}
