<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalProgressionRunner;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalProgressionRunnerTest
 */
#[Package('framework')]
#[AsMessageHandler(handles: ProgressApprovalFlowsTask::class)]
final class ProgressApprovalFlowsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTasks
     */
    public function __construct(EntityRepository $scheduledTasks, LoggerInterface $logger, private readonly ApprovalProgressionRunner $progression)
    {
        parent::__construct($scheduledTasks, $logger);
    }

    public function run(): void
    {
        $this->progression->run();
    }
}
