<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event\EventData;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class FormDataObjectType extends ObjectType
{
    final public const MARKER = 'formData';

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [self::MARKER => true]);
    }
}
