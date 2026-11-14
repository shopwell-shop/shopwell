<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Write;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublicationTest
 */
#[Package('framework')]
final readonly class WorkflowDefinitionWriteProtection implements EventSubscriberInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== WorkflowExecutionDefinitionEntity::ENTITY_NAME) {
                continue;
            }
            $id = $command->getPrimaryKey()['id'] ?? null;
            if ($id !== null && $this->connection->fetchOne('SELECT 1 FROM workflow_release WHERE id = :id', ['id' => $id]) !== false) {
                throw WorkflowException::publicationRejected('immutable_approval_definition');
            }
        }
    }
}
