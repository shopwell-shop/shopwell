<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding\ApprovalBindingWriteGuardTest
 */
#[Package('framework')]
final class ApprovalBindingWriteGuard implements EventSubscriberInterface
{
    public const WRITE_SCOPE = 'approval-flow-binding-write';

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
     * @param \Closure(Context): void $write
     */
    public function write(Context $context, \Closure $write): void
    {
        $this->pendingWrites->offsetSet($context, true);
        try {
            $context->scope(self::WRITE_SCOPE, $write);
        } finally {
            $this->pendingWrites->offsetUnset($context);
        }
    }

    public function admitWrite(EntityWriteEvent $event): void
    {
        $commands = array_filter(
            $event->getCommands(),
            static fn (WriteCommand $command): bool => \in_array($command->getEntityName(), [ApprovalBindingDefinition::ENTITY_NAME, ApprovalBindingHeadDefinition::ENTITY_NAME], true),
        );
        if ($commands === []) {
            return;
        }
        $context = $event->getContext();
        $allowed = $context->getScope() === self::WRITE_SCOPE && $this->pendingWrites->offsetExists($context);
        $this->pendingWrites->offsetUnset($context);
        if (!$allowed) {
            throw ApprovalFlowException::uncontrolledBindingWrite();
        }
        foreach ($commands as $command) {
            if ($command->getEntityName() === ApprovalBindingDefinition::ENTITY_NAME && $command instanceof InsertCommand) {
                $this->admittedCommands->offsetSet($command, $command->getPayload());
                continue;
            }

            throw ApprovalFlowException::uncontrolledBindingWrite();
        }
    }

    public function validateWrite(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!\in_array($command->getEntityName(), [ApprovalBindingDefinition::ENTITY_NAME, ApprovalBindingHeadDefinition::ENTITY_NAME], true)) {
                continue;
            }
            if (!$this->admittedCommands->offsetExists($command) || $this->admittedCommands->offsetGet($command) !== $command->getPayload()) {
                throw ApprovalFlowException::uncontrolledBindingWrite();
            }
            // Keep the exact command identity for DAL deadlock retries; weak keys expire with the batch.
        }
    }
}
