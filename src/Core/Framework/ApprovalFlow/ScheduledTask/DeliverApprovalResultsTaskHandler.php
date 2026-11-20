<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDispatcher;
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
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalResultHandlerTest
 */
#[Package('framework')]
#[AsMessageHandler(handles: DeliverApprovalResultsTask::class)]
final class DeliverApprovalResultsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTasks
     */
    public function __construct(EntityRepository $scheduledTasks, LoggerInterface $logger, private readonly ApprovalResultDispatcher $results)
    {
        parent::__construct($scheduledTasks, $logger);
    }

    public function run(): void
    {
        $this->results->dispatchPending();
    }
}
