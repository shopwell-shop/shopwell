<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class TaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        Context::createDefaultContext();
    }
}
