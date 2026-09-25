<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * Stores a JSON formatted value list. This can be typed using the third constructor parameter.
 *
 * Definition example:
 *
 *      // allow every type
 *      new ListField('product_ids', 'productIds');
 *
 *      // allow int types only
 *      new ListField('product_ids', 'productIds', IntField::class);
 *
 * Output in database:
 *
 *      // mixed type value
 *      ['this is a string', 'another string', true, 15]
 *
 *      // single type values
 *      [12,55,192,22]
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ListField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        private readonly ?string $fieldType = null
    ) {
        parent::__construct($storageName, $propertyName);
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    protected function getSerializerClass(): string
    {
        return ListFieldSerializer::class;
    }
}
