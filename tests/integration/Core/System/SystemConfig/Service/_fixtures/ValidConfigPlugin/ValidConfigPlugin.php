<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin;

use Shopwell\Core\Framework\Plugin;

/**
 * @internal
 */
class ValidConfigPlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
