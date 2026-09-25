<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class PreWriteValidationEvent extends Event implements ShopwellEvent
{
    /**
     * @param list<WriteCommand> $commands
     */
    public function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands
    ) {
    }

    public function getContext(): Context
    {
        return $this->writeContext->getContext();
    }

    public function getWriteContext(): WriteContext
    {
        return $this->writeContext;
    }

    /**
     * @return list<WriteCommand>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return list<WriteCommand>
     */
    public function getCommandsForEntity(string $entity): array
    {
        return array_values(array_filter(
            $this->commands,
            static fn (WriteCommand $command): bool => $command->getEntityName() === $entity
        ));
    }

    public function getExceptions(): WriteException
    {
        return $this->writeContext->getExceptions();
    }

    /**
     * @return list<array<string, string>>
     */
    public function getPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity);
    }

    /**
     * @return list<array<string, string>>
     */
    public function getDeletedPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity, static fn (WriteCommand $command) => $command instanceof DeleteCommand);
    }

    /**
     * @return list<array<string, string>>
     */
    private function findPrimaryKeys(string $entity, ?\Closure $closure = null): array
    {
        $ids = [];

        foreach ($this->commands as $command) {
            if ($command->getEntityName() !== $entity) {
                continue;
            }

            if ($closure instanceof \Closure && !$closure($command)) {
                continue;
            }

            $ids[] = $command->getPrimaryKey();
        }

        return $ids;
    }
}
