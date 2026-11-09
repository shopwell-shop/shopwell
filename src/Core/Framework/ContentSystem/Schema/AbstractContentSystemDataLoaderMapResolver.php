<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Schema;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemDataLoaderMapResolver
{
    abstract public function resolve(): ContentSystemDataLoaderMap;
}
