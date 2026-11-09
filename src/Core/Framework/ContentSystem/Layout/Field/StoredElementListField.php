<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Field;

use Shopwell\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\Log\Package;

/**
 * The `content_layout` layout column's field. Its items carry no per-item `Field` type: a stored element is a
 * fixed wire shape defined by {@see StoredElementCodec},
 * not a DAL field, so the generated Admin-API schema describes the item as a closed object rather than naming
 * a field class whose serializer would have to exist to say anything more.
 *
 * @internal
 */
#[Package('framework')]
class StoredElementListField extends ListField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName, null);
    }

    protected function getSerializerClass(): string
    {
        return StoredElementListFieldSerializer::class;
    }
}
