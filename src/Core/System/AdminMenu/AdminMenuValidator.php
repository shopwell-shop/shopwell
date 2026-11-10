<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Guards the invariants of the navigation table during every DAL write, no matter which
 * route or service issued it:
 *
 * - Core built-in items (`sys_locked`) cannot be deleted. They can be customized and
 *   hidden, but never lost, so a misbehaving client cannot take the navigation apart.
 * - An `internal_key` must be declared by a provider, so a menu item can never point at
 *   a route the administration does not register.
 * - A write of an administrator-customizable field marks the item as overridden, so the
 *   plugin lifecycle knows which items to preserve on the next plugin update. Writes
 *   that carry system fields are lifecycle writes and leave the flag alone.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class AdminMenuValidator implements EventSubscriberInterface
{
    final public const VIOLATION_SYS_LOCKED_DELETE = 'admin-menu-sys-locked-delete-violation';

    final public const VIOLATION_ROUTE_NOT_WHITELISTED = 'admin-menu-route-not-whitelisted-violation';

    /**
     * Fields an administrator customizes through the management UI. A write touching any
     * of them marks the item as overridden.
     */
    private const CUSTOMIZABLE_FIELDS = [
        'internal_key',
        'route_key',
        'group_name',
        'icon',
        'color',
        'type',
        'link_target',
        'privilege',
        'active',
        'parent_id',
        'after_menu_item_id',
    ];

    /**
     * Fields only the plugin lifecycle and core services write. A payload carrying one
     * of them is a lifecycle write, not an administrator customization.
     */
    private const SYSTEM_FIELDS = [
        'sys_locked',
        'plugin_id',
        'default_meta',
        'config_overridden',
        'archived',
    ];

    /**
     * A guard against data that already broke its own tree: the walk below stops instead of
     * following a cycle forever.
     */
    private const MAX_SUBTREE_DEPTH = 10;

    /**
     * @internal
     *
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $menuItemRepository,
        private readonly AdminMenuRouteRegistry $routeRegistry,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
            EntityWrittenContainerEvent::class => 'markOverriddenAfterWrite',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommandsForEntity(AdminMenuItemDefinition::ENTITY_NAME);

        if ($commands === []) {
            return;
        }

        if ($event->getContext()->hasExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION)) {
            // The declaration synchronization writes what a provider declares: internal keys
            // the container may not know yet - a plugin installed in this very request -
            // ordering changes that are not an administrator decision, and the deletion of
            // rows a release no longer declares. The lock exists to stop a client from
            // taking the navigation apart, not to stop the core from maintaining it.
            // An entry whose declaration the registry misses stays hidden in
            // {@link VisibleMenuTreeBuilder}, which is the check that matters for rendering.
            return;
        }

        $this->guardSysLockedItems($commands, $event);
        $this->guardRouteWhitelist($commands, $event);
        $this->markOverriddenItems($commands);
    }

    /**
     * The title of an item is customized through its translation row, and a translation
     * write cannot carry the flag of the owning item. The write pipeline has already
     * executed at this point, so the flag is written back in a follow-up write of the
     * same transaction. The follow-up payload carries `config_overridden` itself, which
     * makes {@link markOverriddenItems} skip it - the write never recurses.
     */
    public function markOverriddenAfterWrite(EntityWrittenContainerEvent $event): void
    {
        if ($event->getContext()->hasExtension(PluginMenuSynchronizer::CONTEXT_EXTENSION)) {
            // the declaration synchronization writes the labels of the items it owns, which
            // is not an administrator customization
            return;
        }

        $translationEvent = $event->getEventByEntityName(AdminMenuItemTranslationDefinition::ENTITY_NAME);

        if (!$translationEvent) {
            return;
        }

        $itemIds = [];

        foreach ($translationEvent->getWriteResults() as $result) {
            if ($result->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $menuItemId = $result->getPrimaryKey()['admin_menu_item_id'] ?? null;

            if (\is_string($menuItemId)) {
                $itemIds[] = $menuItemId;
            }
        }

        if ($itemIds === []) {
            return;
        }

        $this->menuItemRepository->update(
            array_map(static fn (string $id): array => [
                'id' => $id,
                'configOverridden' => true,
            ], array_values(array_unique($itemIds))),
            Context::createDefaultContext()
        );
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function guardSysLockedItems(array $commands, PreWriteValidationEvent $event): void
    {
        $deletingIds = [];

        foreach ($commands as $command) {
            if (!$command instanceof DeleteCommand) {
                continue;
            }

            $deletingIds[] = mb_strtolower(Uuid::fromBytesToHex($command->getPrimaryKey()['id']));
        }

        if ($deletingIds === []) {
            return;
        }

        $lockedIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM admin_menu_item WHERE id IN (:ids) AND sys_locked = 1',
            ['ids' => Uuid::fromHexToBytesList($deletingIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        // `fk.admin_menu_item.parent_id` cascades, so deleting a folder removes its children
        // in the database without a delete command of their own. A locked item below a folder
        // would disappear without ever reaching the check above, so the subtree counts as well.
        $lockedIds = [...$lockedIds, ...$this->findLockedDescendants($deletingIds)];

        if ($lockedIds === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($lockedIds as $id) {
            $violations->add(new ConstraintViolation(
                \sprintf('The core menu item %s is locked and cannot be deleted.', $id),
                'The core menu item {{ id }} is locked and cannot be deleted.',
                ['{{ id }}' => $id],
                null,
                '/' . $id,
                $id,
                null,
                self::VIOLATION_SYS_LOCKED_DELETE
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param list<string> $parentIds hex ids of the items being deleted
     *
     * @return list<string> hex ids of the locked items below them
     */
    private function findLockedDescendants(array $parentIds): array
    {
        $locked = [];
        $frontier = $parentIds;
        $depth = 0;

        while ($frontier !== [] && $depth < self::MAX_SUBTREE_DEPTH) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT LOWER(HEX(id)) AS id, sys_locked FROM admin_menu_item WHERE parent_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($frontier)],
                ['ids' => ArrayParameterType::BINARY]
            );

            $frontier = [];

            foreach ($rows as $row) {
                $frontier[] = (string) $row['id'];

                if ((bool) $row['sys_locked']) {
                    $locked[] = (string) $row['id'];
                }
            }

            ++$depth;
        }

        return $locked;
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function guardRouteWhitelist(array $commands, PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $internalKey = $command->getPayload()['route_key'] ?? $command->getPayload()['internal_key'] ?? null;

            if (($command->getPayload()['type'] ?? null) === AdminMenuItemDefinition::TYPE_GROUP || !\is_string($internalKey) || ($this->routeRegistry->has($internalKey) && (!isset($command->getPayload()['route_key']) || $this->routeRegistry->get($internalKey)?->type === AdminMenuItemDefinition::TYPE_MENU))) {
                continue;
            }

            $violations->add(new ConstraintViolation(
                \sprintf(
                    'The internal key %s is not registered by any admin menu provider and cannot be used.',
                    $internalKey
                ),
                'The internal key {{ internalKey }} is not registered by any admin menu provider and cannot be used.',
                ['{{ internalKey }}' => $internalKey],
                null,
                '/internalKey',
                $internalKey,
                null,
                self::VIOLATION_ROUTE_NOT_WHITELISTED
            ));
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function markOverriddenItems(array $commands): void
    {
        foreach ($commands as $command) {
            if (!$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();

            if (\array_key_exists('config_overridden', $payload)) {
                continue;
            }

            $touchesCustomization = (bool) array_intersect(self::CUSTOMIZABLE_FIELDS, array_keys($payload));
            $isLifecycleWrite = (bool) array_intersect(self::SYSTEM_FIELDS, array_keys($payload));

            if (!$touchesCustomization || $isLifecycleWrite) {
                continue;
            }

            $command->addPayload('config_overridden', true);
        }
    }
}
