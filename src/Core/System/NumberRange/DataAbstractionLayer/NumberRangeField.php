<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class NumberRangeField extends StringField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        int $maxLength = 64
    ) {
        parent::__construct($storageName, $propertyName, $maxLength);
    }
}
