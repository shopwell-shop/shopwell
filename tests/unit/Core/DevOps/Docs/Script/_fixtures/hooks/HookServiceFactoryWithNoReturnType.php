<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

class HookServiceFactoryWithNoReturnType
{
    public function factory(Hook $hook, Script $script): SimpleService|bool
    {
        return new SimpleService();
    }

    public function getName(): string
    {
        return 'no_return_type_service';
    }
}
