<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ConfigJsonFieldSerializer extends JsonFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        $wrapped = [ConfigJsonField::STORAGE_KEY => $data->getValue()];
        $data->setValue($wrapped);

        return parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): mixed
    {
        $wrapped = parent::decode($field, $value);
        if ($wrapped === null || !isset($wrapped[ConfigJsonField::STORAGE_KEY])) {
            return null;
        }

        return $wrapped[ConfigJsonField::STORAGE_KEY];
    }
}
