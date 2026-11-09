<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemStyleOptionLoader
{
    /**
     * @return list<StyleOptionSpecification>
     */
    abstract public function load(): array;
}
