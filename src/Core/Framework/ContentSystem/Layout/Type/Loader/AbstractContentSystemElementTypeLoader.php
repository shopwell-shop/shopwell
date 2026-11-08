<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemElementTypeLoader
{
    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    abstract public function load(): array;
}
