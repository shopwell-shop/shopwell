<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Binding\Loader;

use Shopwell\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemBindingSpecificationLoader
{
    /**
     * @return list<BindingSpecification>
     */
    abstract public function load(): array;
}
