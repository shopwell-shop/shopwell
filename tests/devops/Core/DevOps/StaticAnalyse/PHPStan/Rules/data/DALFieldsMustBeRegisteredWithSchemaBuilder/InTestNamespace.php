<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class InTestNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
