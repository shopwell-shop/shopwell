<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Field;

use Shopwell\SomewhereElse\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class NotInCoreNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
