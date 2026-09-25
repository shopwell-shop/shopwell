<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\Mock;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin;

/**
 * @internal
 */
#[Package('discovery')]
class TestPlugin extends Plugin
{
    protected string $name;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
