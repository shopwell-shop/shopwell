<?php

declare(strict_types=1);

namespace Shopwell\Tests\Core\Framework\Foo;

use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

class TestEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
