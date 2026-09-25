<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Foo;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

class Bar
{
    public function foo(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'ccc';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection(
                    [new StringField('aaa', 'foo')]
                );
            }
        };
    }
}
