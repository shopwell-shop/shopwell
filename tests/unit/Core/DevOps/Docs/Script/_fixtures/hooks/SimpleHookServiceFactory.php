<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopwell\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\Framework\Script\Execution\Script;

class SimpleHookServiceFactory extends HookServiceFactory
{
    public function factory(Hook $hook, Script $script): SimpleService
    {
        return new SimpleService();
    }

    public function getName(): string
    {
        return 'simple_service';
    }
}
