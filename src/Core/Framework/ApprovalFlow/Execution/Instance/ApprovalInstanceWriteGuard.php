<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuardTest
 */
#[Package('framework')]
final class ApprovalInstanceWriteGuard implements EventSubscriberInterface
{
    private const ENTITIES = [
        ApprovalInstanceDefinition::ENTITY_NAME,
        ApprovalOperationDefinition::ENTITY_NAME,
        ApprovalNodeExecutionDefinition::ENTITY_NAME,
        ApprovalTaskDefinition::ENTITY_NAME,
    ];

    /**
     * @var \WeakMap<Context, true>
     */
    private \WeakMap $pendingWrites;

    /**
     * @var \WeakMap<WriteCommand, array<string, mixed>>
     */
    private \WeakMap $admittedCommands;

    public function __construct()
    {
        $this->pendingWrites = new \WeakMap();
        $this->admittedCommands = new \WeakMap();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => ['admitWrite', \PHP_INT_MAX],
            PreWriteValidationEvent::class => ['validateWrite', \PHP_INT_MIN],
        ];
    }

    /**
     * Permits one DAL batch, not another write from an event or a success/error callback.
     *
     * @param \Closure(Context): void $write
     */
    public function write(Context $context, \Closure $write): void
    {
        $this->pendingWrites[$context] = true;
        try {
            $context->scope(Context::SYSTEM_SCOPE, $write);
        } finally {
            unset($this->pendingWrites[$context]);
        }
    }

    public function admitWrite(EntityWriteEvent $event): void
    {
        $commands = array_filter(
            $event->getCommands(),
            static fn (WriteCommand $command): bool => \in_array($command->getEntityName(), self::ENTITIES, true),
        );
        if ($commands === []) {
            return;
        }
        $context = $event->getContext();
        $allowed = $context->getScope() === Context::SYSTEM_SCOPE && isset($this->pendingWrites[$context]);
        // Consume before ordinary listeners run; system scope alone never authorizes another batch.
        unset($this->pendingWrites[$context]);
        if (!$allowed) {
            throw ApprovalFlowException::uncontrolledInstanceWrite();
        }
        foreach ($commands as $command) {
            if ($command instanceof InsertCommand && $command->getEntityName() !== ApprovalInstanceDefinition::ENTITY_NAME) {
                continue;
            }
            if ($command->getEntityName() === ApprovalInstanceDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'outcome', 'aggregate_version', 'updated_at']) === []) {
                continue;
            }
            if ($command->getEntityName() === ApprovalNodeExecutionDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'approval_policy', 'outcome', 'updated_at']) === []) {
                continue;
            }
            if ($command->getEntityName() === ApprovalTaskDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'completion_command_id', 'updated_at']) === []) {
                continue;
            }

            throw ApprovalFlowException::uncontrolledInstanceWrite();
        }
        foreach ($commands as $command) {
            $this->admittedCommands[$command] = $command->getPayload();
        }
    }

    public function validateWrite(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!\in_array($command->getEntityName(), self::ENTITIES, true)) {
                continue;
            }
            if (!isset($this->admittedCommands[$command]) || $this->admittedCommands[$command] !== $command->getPayload()) {
                throw ApprovalFlowException::uncontrolledInstanceWrite();
            }
            // Keep the exact command identity for DAL deadlock retries; weak keys expire with the batch.
        }
    }
}
