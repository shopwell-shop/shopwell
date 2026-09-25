<?php
declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class PHPUnserializeFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        throw DataAbstractionLayerException::serializedFieldRequiresIndexer();
    }

    public function decode(Field $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        /** @phpstan-ignore shopwell.unserializeUsage */
        return \unserialize($value);
    }
}
