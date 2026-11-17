<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderServiceTest
 */
#[Package('framework')]
#[AsMessageHandler(handles: ApprovalReminderTask::class)]
final class ApprovalReminderTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTasks
     */
    public function __construct(EntityRepository $scheduledTasks, LoggerInterface $logger, private readonly ApprovalReminderService $reminders)
    {
        parent::__construct($scheduledTasks, $logger);
    }

    public function run(): void
    {
        $this->reminders->runDue();
    }
}
