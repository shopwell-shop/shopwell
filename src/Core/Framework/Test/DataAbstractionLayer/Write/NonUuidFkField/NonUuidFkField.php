<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;

/**
 * @internal test class
 */
class NonUuidFkField extends FkField
{
    protected function getSerializerClass(): string
    {
        return NonUuidFkFieldSerializer::class;
    }
}
