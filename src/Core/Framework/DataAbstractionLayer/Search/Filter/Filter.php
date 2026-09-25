<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
abstract class Filter extends Struct implements CriteriaPartInterface
{
    /**
     * Include the class name in the json serialization.
     * So the criteria hash is different for different filter types when the same field and value is used.
     *
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        $value = parent::jsonSerialize();
        $value['_class'] = static::class;

        return $value;
    }
}
