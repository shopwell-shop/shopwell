<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer;

class AttributeEntityDefinition extends EntityDefinition
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
