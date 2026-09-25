<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
class FooBarBundle extends Bundle
{
    protected string $name = 'FancyBundleName';
}
