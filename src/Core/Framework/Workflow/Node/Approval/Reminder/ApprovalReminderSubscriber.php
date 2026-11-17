<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderServiceTest
 */
#[Package('framework')]
final readonly class ApprovalReminderSubscriber implements EventSubscriberInterface
{
    public function __construct(private ApprovalReminderStore $store, private WorkflowTransactionRunner $transactions)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return ['workflow_task.written' => 'tasksWritten', PreWriteValidationEvent::class => 'protect'];
    }

    /**
     * @param EntityWrittenEvent<string> $event
     */
    public function tasksWritten(EntityWrittenEvent $event): void
    {
        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                $ids[] = $result->getPrimaryKey();
            }
        }
        $this->store->initialize($ids, $event->getContext());
    }

    public function protect(PreWriteValidationEvent $event): void
    {
        if (!$event->getContext()->getSource() instanceof AdminApiSource || $this->transactions->isRunning()) {
            return;
        }
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === ApprovalReminderEntity::ENTITY_NAME) {
                throw WorkflowExecutionException::forbidden('Approval reminders can only be changed through reminder actions.');
            }
        }
    }
}
