<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\AssociationField;

/**
 * @internal
 */
class MyAssociationField extends AssociationField
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
