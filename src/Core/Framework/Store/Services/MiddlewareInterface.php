<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Services;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
interface MiddlewareInterface
{
    public function __invoke(callable $handler): callable;
}
