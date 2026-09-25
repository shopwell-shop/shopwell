<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Acl;

use Shopwell\Core\Framework\Api\Acl\Event\CommandAclValidationEvent;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class AclWriteValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $source = $event->getContext()->getSource();
        if ($source instanceof AdminSalesChannelApiSource) {
            $context = $source->getOriginalContext();
            $source = $context->getSource();
        }

        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $commands = $event->getCommands();
        $missingPrivileges = [];

        foreach ($commands as $command) {
            $resource = $command->getEntityName();
            $privilege = $command->getPrivilege();

            if ($privilege === null) {
                continue;
            }

            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                $resource = $definition->getParentDefinition()->getEntityName();

                if ($privilege !== AclRoleDefinition::PRIVILEGE_DELETE) {
                    $privilege = $this->getPrivilegeForParentWriteOperation($command, $commands);
                }
            }

            if (!$source->isAllowed($resource . ':' . $privilege)) {
                $missingPrivileges[] = $resource . ':' . $privilege;
            }

            $event = new CommandAclValidationEvent($missingPrivileges, $source, $command);
            $this->eventDispatcher->dispatch($event);
            $missingPrivileges = $event->getMissingPrivileges();
        }

        $this->tryToThrow($missingPrivileges);
    }

    /**
     * @param list<string> $missingPrivileges
     */
    private function tryToThrow(array $missingPrivileges): void
    {
        if ($missingPrivileges !== []) {
            throw ApiException::missingPrivileges($missingPrivileges);
        }
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function getPrivilegeForParentWriteOperation(WriteCommand $command, array $commands): string
    {
        $pathSuffix = '/translations/' . Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $parentCommandPath = str_replace($pathSuffix, '', $command->getPath());
        $parentCommand = $this->findCommandByPath($parentCommandPath, $commands);

        // writes to translation need privilege from parent command
        // if we update e.g. a product and add translations for a new language
        // the writeCommand on the translation would be an insert
        if ($parentCommand) {
            return (string) $parentCommand->getPrivilege();
        }

        // if we don't have a parentCommand it must be a update,
        // because the parentEntity must already exist
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function findCommandByPath(string $commandPath, array $commands): ?WriteCommand
    {
        foreach ($commands as $command) {
            if ($command->getPath() === $commandPath) {
                return $command;
            }
        }

        return null;
    }
}
