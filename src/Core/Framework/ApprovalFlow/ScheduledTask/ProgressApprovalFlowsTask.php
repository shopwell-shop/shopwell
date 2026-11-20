<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalProgressionRunnerTest
 */
#[Package('framework')]
class ProgressApprovalFlowsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'approval_flow.progress';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
