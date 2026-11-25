<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDefinition;
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
    public const REQUEST_SCOPE = 'approval_flow_request';

    private const COMMAND_WRITE = 'command';

    private const REQUEST_WRITE = 'request';

    private const ENTITIES = [
        ApprovalInstanceDefinition::ENTITY_NAME,
        ApprovalRequestDefinition::ENTITY_NAME,
        ApprovalOperationDefinition::ENTITY_NAME,
        ApprovalNodeExecutionDefinition::ENTITY_NAME,
        ApprovalTaskDefinition::ENTITY_NAME,
        ApprovalResultDefinition::ENTITY_NAME,
    ];

    /**
     * @var \WeakMap<Context, self::COMMAND_WRITE|self::REQUEST_WRITE>
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
        $this->runWrite($context, $write, self::COMMAND_WRITE);
    }

    /**
     * @param \Closure(Context): void $write
     */
    public function create(Context $context, \Closure $write): void
    {
        $this->runWrite($context, $write, self::REQUEST_WRITE);
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
        $mode = $this->pendingWrites->offsetExists($context) ? $this->pendingWrites->offsetGet($context) : null;
        $expectedScope = $mode === self::REQUEST_WRITE ? self::REQUEST_SCOPE : Context::SYSTEM_SCOPE;
        $allowed = $context->getScope() === $expectedScope && $mode !== null;
        // Consume before ordinary listeners run; system scope alone never authorizes another batch.
        $this->pendingWrites->offsetUnset($context);
        if (!$allowed) {
            throw ApprovalFlowException::uncontrolledInstanceWrite();
        }
        foreach ($commands as $command) {
            if ($mode === self::REQUEST_WRITE) {
                if (!$command instanceof InsertCommand || !\in_array($command->getEntityName(), [ApprovalRequestDefinition::ENTITY_NAME, ApprovalInstanceDefinition::ENTITY_NAME], true)) {
                    throw ApprovalFlowException::uncontrolledInstanceWrite();
                }
                if ($command->getEntityName() === ApprovalInstanceDefinition::ENTITY_NAME
                    && (($command->getPayload()['status'] ?? null) !== ApprovalInstanceStatus::Draft->value || ($command->getPayload()['aggregate_version'] ?? null) !== 1 || ($command->getPayload()['outcome'] ?? null) !== null)) {
                    throw ApprovalFlowException::uncontrolledInstanceWrite();
                }
                continue;
            }
            if ($command->getEntityName() === ApprovalRequestDefinition::ENTITY_NAME) {
                throw ApprovalFlowException::uncontrolledInstanceWrite();
            }
            if ($command instanceof InsertCommand && $command->getEntityName() !== ApprovalInstanceDefinition::ENTITY_NAME) {
                continue;
            }
            if ($command->getEntityName() === ApprovalInstanceDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'outcome', 'aggregate_version', 'updated_at']) === []) {
                continue;
            }
            if ($command->getEntityName() === ApprovalNodeExecutionDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'approval_policy', 'approval_policy_version', 'outcome', 'updated_at']) === []) {
                continue;
            }
            if ($command->getEntityName() === ApprovalTaskDefinition::ENTITY_NAME && $command instanceof UpdateCommand
                && array_diff(array_keys($command->getPayload()), ['status', 'completion_command_id', 'updated_at']) === []) {
                continue;
            }

            throw ApprovalFlowException::uncontrolledInstanceWrite();
        }
        foreach ($commands as $command) {
            $this->admittedCommands->offsetSet($command, $command->getPayload());
        }
    }

    public function validateWrite(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!\in_array($command->getEntityName(), self::ENTITIES, true)) {
                continue;
            }
            if (!$this->admittedCommands->offsetExists($command) || $this->admittedCommands->offsetGet($command) !== $command->getPayload()) {
                throw ApprovalFlowException::uncontrolledInstanceWrite();
            }
            // Keep the exact command identity for DAL deadlock retries; weak keys expire with the batch.
        }
    }

    /**
     * @param \Closure(Context): void $write
     * @param self::COMMAND_WRITE|self::REQUEST_WRITE $mode
     */
    private function runWrite(Context $context, \Closure $write, string $mode): void
    {
        $this->pendingWrites->offsetSet($context, $mode);
        try {
            $context->scope($mode === self::REQUEST_WRITE ? self::REQUEST_SCOPE : Context::SYSTEM_SCOPE, $write);
        } finally {
            $this->pendingWrites->offsetUnset($context);
        }
    }
}
