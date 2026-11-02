<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuValidator;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuValidator::class)]
class AdminMenuValidatorTest extends TestCase
{
    private AdminMenuItemDefinition $menuItemDefinition;

    private Context $context;

    /**
     * Binary id of the item the commands write, as the DAL stores ids as bytes.
     */
    private string $itemId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->itemId = Uuid::randomBytes();

        // the definition has to know its registry, as the write commands decode their
        // primary key through it while being constructed
        $registry = new StaticDefinitionInstanceRegistry(
            [AdminMenuItemDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->get(AdminMenuItemDefinition::class);
        static::assertInstanceOf(AdminMenuItemDefinition::class, $definition);
        $this->menuItemDefinition = $definition;
    }

    public function testRejectsAnInternalKeyNoProviderDeclares(): void
    {
        $command = $this->createUpdateCommand(['internal_key' => 'sw.renamed.route']);

        $event = $this->validate([$command]);

        $errors = iterator_to_array($event->getExceptions()->getErrors());

        static::assertSame(AdminMenuValidator::VIOLATION_ROUTE_NOT_WHITELISTED, $errors[0]['code']);
    }

    public function testAcceptsAnInternalKeyAProviderDeclares(): void
    {
        $command = $this->createUpdateCommand(['internal_key' => 'sw-product']);

        $event = $this->validate([$command]);

        static::assertSame([], iterator_to_array($event->getExceptions()->getErrors()));
    }

    public function testCustomizingAFieldMarksTheItemAsOverridden(): void
    {
        $command = $this->createUpdateCommand(['icon' => 'regular-star']);

        $this->validate([$command]);

        static::assertTrue($command->getPayload()['config_overridden']);
    }

    public function testLifecycleWritesLeaveTheOverriddenFlagAlone(): void
    {
        $command = $this->createUpdateCommand([
            'icon' => 'regular-star',
            'default_meta' => ['icon' => 'regular-star'],
        ]);

        $this->validate([$command]);

        static::assertArrayNotHasKey('config_overridden', $command->getPayload());
    }

    public function testInsertingAMenuItemIsValidatedAgainstTheWhitelistAsWell(): void
    {
        $command = $this->createInsertCommand(['internal_key' => 'sw.not-declared']);

        $event = $this->validate([$command]);

        $errors = iterator_to_array($event->getExceptions()->getErrors());

        static::assertSame(AdminMenuValidator::VIOLATION_ROUTE_NOT_WHITELISTED, $errors[0]['code']);
    }

    public function testDeletingALockedItemIsRejected(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([Uuid::fromBytesToHex($this->itemId)]);

        $event = $this->validate([$this->createDeleteCommand($this->itemId)], $connection);

        $errors = iterator_to_array($event->getExceptions()->getErrors());

        static::assertSame(AdminMenuValidator::VIOLATION_SYS_LOCKED_DELETE, $errors[0]['code']);
    }

    public function testDeletingAnUnlockedItemIsAllowed(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $event = $this->validate([$this->createDeleteCommand($this->itemId)], $connection);

        static::assertSame([], iterator_to_array($event->getExceptions()->getErrors()));
    }

    public function testDeletingAFolderWithALockedItemBelowItIsRejected(): void
    {
        // the folder row itself is not locked, its child is - and the cascade would remove
        // the child without a delete command that could be checked
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => Uuid::fromBytesToHex($this->itemId), 'sys_locked' => 1],
        ]);

        $event = $this->validate([$this->createDeleteCommand(Uuid::randomBytes())], $connection);

        $errors = iterator_to_array($event->getExceptions()->getErrors());

        static::assertSame(AdminMenuValidator::VIOLATION_SYS_LOCKED_DELETE, $errors[0]['code']);
    }

    public function testDeletingAFolderWithOnlyUnlockedItemsBelowItIsAllowed(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => Uuid::fromBytesToHex($this->itemId), 'sys_locked' => 0],
        ]);

        $event = $this->validate([$this->createDeleteCommand(Uuid::randomBytes())], $connection);

        static::assertSame([], iterator_to_array($event->getExceptions()->getErrors()));
    }

    public function testRenamingATranslationMarksTheOwningItemAsOverridden(): void
    {
        $menuItemRepository = StaticEntityRepository::of(AdminMenuItemCollection::class);

        $this->createValidator($menuItemRepository)->markOverriddenAfterWrite(
            $this->createTranslationWriteEvent(EntityWriteResult::OPERATION_UPDATE)
        );

        static::assertSame(
            [[['id' => $this->itemId, 'configOverridden' => true]]],
            $menuItemRepository->updates
        );
    }

    public function testSeedingATranslationDoesNotMarkTheItemAsOverridden(): void
    {
        $menuItemRepository = StaticEntityRepository::of(AdminMenuItemCollection::class);

        $this->createValidator($menuItemRepository)->markOverriddenAfterWrite(
            $this->createTranslationWriteEvent(EntityWriteResult::OPERATION_INSERT)
        );

        static::assertSame([], $menuItemRepository->updates);
    }

    public function testWritesOfOtherEntitiesAreNotFollowedUp(): void
    {
        $menuItemRepository = StaticEntityRepository::of(AdminMenuItemCollection::class);
        $context = Context::createDefaultContext();

        $this->createValidator($menuItemRepository)->markOverriddenAfterWrite(new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(AdminMenuItemDefinition::ENTITY_NAME, [], $context),
            ]),
            []
        ));

        static::assertSame([], $menuItemRepository->updates);
    }

    public function testWritesOfTheDeclarationSyncSkipTheWhitelist(): void
    {
        // a plugin installed in this request is not in the container yet, its declarations
        // still have to be seeded
        $command = $this->createInsertCommand(['internal_key' => 'sw.not-loaded-yet']);

        $event = $this->validate([$command], null, $this->createSynchronizationContext());

        static::assertSame([], iterator_to_array($event->getExceptions()->getErrors()));
    }

    public function testWritesOfTheDeclarationSyncAreNoCustomization(): void
    {
        // ordering the declaration into the chain touches customizable fields
        $command = $this->createUpdateCommand(['parent_id' => null, 'after_menu_item_id' => null]);

        $this->validate([$command], null, $this->createSynchronizationContext());

        static::assertArrayNotHasKey('config_overridden', $command->getPayload());
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function validate(array $commands, ?Connection $connection = null, ?Context $context = null): PreWriteValidationEvent
    {
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context ?? $this->context),
            $commands
        );

        $this->createValidator(null, $connection)->preValidate($event);

        return $event;
    }

    private function createSynchronizationContext(): Context
    {
        $context = Context::createDefaultContext();
        $context->addExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION, new ArrayStruct());

        return $context;
    }

    /**
     * @param EntityRepository<AdminMenuItemCollection>|null $menuItemRepository
     */
    private function createValidator(?EntityRepository $menuItemRepository = null, ?Connection $connection = null): AdminMenuValidator
    {
        $provider = new class implements AdminMenuDefinitionProviderInterface {
            public function getPluginTechnicalName(): ?string
            {
                return null;
            }

            public function getDefinitions(): array
            {
                return [new AdminMenuDefinitionStruct('sw-product')];
            }
        };

        return new AdminMenuValidator(
            $connection ?? static::createStub(Connection::class),
            $menuItemRepository ?? StaticEntityRepository::of(AdminMenuItemCollection::class),
            new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider]))
        );
    }

    private function createTranslationWriteEvent(string $operation): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    AdminMenuItemTranslationDefinition::ENTITY_NAME,
                    [new EntityWriteResult(
                        ['admin_menu_item_id' => $this->itemId],
                        ['title' => 'Ein eigener Titel'],
                        AdminMenuItemTranslationDefinition::ENTITY_NAME,
                        $operation
                    )],
                    $context
                ),
            ]),
            []
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createUpdateCommand(array $payload, ?string $itemId = null): UpdateCommand
    {
        $itemId ??= $this->itemId;

        return new UpdateCommand(
            $this->menuItemDefinition,
            $payload,
            ['id' => $itemId],
            $this->createExistence($itemId),
            '/'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createInsertCommand(array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->menuItemDefinition,
            $payload,
            ['id' => $this->itemId],
            $this->createExistence($this->itemId),
            '/'
        );
    }

    private function createDeleteCommand(string $itemId): DeleteCommand
    {
        return new DeleteCommand(
            $this->menuItemDefinition,
            ['id' => $itemId],
            $this->createExistence($itemId),
        );
    }

    private function createExistence(string $itemId): EntityExistence
    {
        return new EntityExistence(AdminMenuItemDefinition::ENTITY_NAME, ['id' => $itemId], true, false, false, []);
    }
}
