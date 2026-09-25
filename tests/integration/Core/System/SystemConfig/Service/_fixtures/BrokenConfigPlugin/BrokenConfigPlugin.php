<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin;

use Shopwell\Core\Framework\Plugin;

/**
 * @internal
 */
class BrokenConfigPlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
