<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class BulkEntityExtension
{
    /**
     * Constructor is final to ensure the extensions can be built without any dependencies
     */
    final public function __construct()
    {
    }

    /**
     * @return \Generator<string, list<Field>>
     */
    abstract public function collect(): \Generator;
}
