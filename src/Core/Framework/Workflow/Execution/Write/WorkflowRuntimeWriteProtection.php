<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Write;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyEntity;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogEntity;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\Write\WorkflowRuntimeWriteProtectionTest
 */
#[Package('framework')]
final readonly class WorkflowRuntimeWriteProtection implements EventSubscriberInterface
{
    public function __construct(private WorkflowTransactionRunner $transactions)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        // StateMachineRegistry temporarily elevates scope while preserving the API source.
        // Only the approval facade may turn an API actor's request into runtime writes.
        if (!$event->getContext()->getSource() instanceof AdminApiSource || $this->transactions->isRunning()) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (\in_array($command->getEntityName(), [
                WorkflowInstanceEntity::ENTITY_NAME,
                WorkflowNodeExecutionEntity::ENTITY_NAME,
                WorkflowTaskEntity::ENTITY_NAME,
                WorkflowTaskHistoryEntity::ENTITY_NAME,
                WorkflowCopyEntity::ENTITY_NAME,
                WorkflowLogEntity::ENTITY_NAME,
            ], true)) {
                throw WorkflowExecutionException::forbidden('Approval records can only be changed through approval actions.');
            }
        }
    }
}
