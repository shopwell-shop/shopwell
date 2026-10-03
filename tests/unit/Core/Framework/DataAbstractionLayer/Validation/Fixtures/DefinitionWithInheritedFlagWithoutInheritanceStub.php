<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DefinitionWithInheritedFlagWithoutInheritanceStub extends DefinitionWithInheritedAssociationsStub
{
    public function isInheritanceAware(): bool
    {
        return false;
    }
}
