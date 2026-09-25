<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

/**
 * @internal
 */
class MyTranslatedField extends TranslatedField
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
