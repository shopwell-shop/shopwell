<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class NotRegisteredField extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
