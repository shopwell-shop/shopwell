<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Service;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Performs the administration actions on the navigation that are more than a plain field
 * write: moving an item has to repair the `after_menu_item_id` chain of both affected
 * levels and must not create a cycle, and assigning roles writes a mapping collection.
 *
 * Creating, updating and deleting items goes through the generated DAL routes; this
 * service only exists for the actions where the payload of a single row is not enough.
 */
#[Package('fundamentals@framework')]
class AdminMenuConfigService
{
    final public const VIOLATION_ITEM_NOT_FOUND = 'admin-menu-item-not-found-violation';

    final public const VIOLATION_MOVE_UNDER_ITSELF = 'admin-menu-move-under-itself-violation';

    final public const VIOLATION_MOVE_UNDER_DESCENDANT = 'admin-menu-move-under-descendant-violation';

    final public const VIOLATION_AFTER_NOT_SIBLING = 'admin-menu-after-not-sibling-violation';

    final public const VIOLATION_UNKNOWN_ROLE = 'admin-menu-unknown-role-violation';

    /**
     * @internal
     *
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<EntityCollection<Entity>> $menuItemRoleRepository
     */
    public function __construct(
        private readonly EntityRepository $menuItemRepository,
        private readonly EntityRepository $aclRoleRepository,
        private readonly EntityRepository $menuItemRoleRepository,
    ) {
    }

    /**
     * @param list<string> $roleIds
     */
    public function assignRoles(Context $context, string $itemId, array $roleIds): void
    {
        $item = $this->requireItem($context, $itemId);

        $roleIds = array_values(array_unique($roleIds));

        if ($roleIds !== []) {
            $missing = array_diff($roleIds, $this->aclRoleRepository->searchIds(new Criteria($roleIds), $context)->getIds());

            if ($missing !== []) {
                throw $this->violation(
                    \sprintf('The ACL roles %s do not exist.', implode(', ', $missing)),
                    self::VIOLATION_UNKNOWN_ROLE,
                    '/roles',
                    implode(', ', $missing)
                );
            }
        }

        $existing = $item->getRoles()?->getIds() ?? [];
        $added = array_values(array_diff($roleIds, $existing));
        if ($added !== []) {
            $this->menuItemRepository->update([[
                'id' => $itemId,
                'roles' => array_map(static fn (string $id): array => ['id' => $id], $added),
            ]], $context);
        }

        $removed = array_values(array_diff($existing, $roleIds));
        if ($removed !== []) {
            $this->menuItemRoleRepository->delete(array_map(
                static fn (string $id): array => ['adminMenuItemId' => $itemId, 'aclRoleId' => $id],
                $removed
            ), $context);
        }
    }

    public function move(Context $context, string $itemId, ?string $parentId, ?string $afterMenuItemId): void
    {
        $item = $this->requireItem($context, $itemId);

        if ($item->getParentId() === $parentId && $item->getAfterMenuItemId() === $afterMenuItemId) {
            // A newly created root shares its initial position with the old head.
            $updates = $this->takePositionInLevel($context, $itemId, $parentId, $afterMenuItemId);
            if ($updates !== []) {
                $this->menuItemRepository->update($updates, $context);
            }

            return;
        }

        if ($parentId === $itemId) {
            throw $this->violation(
                'A menu item cannot be moved below itself.',
                self::VIOLATION_MOVE_UNDER_ITSELF,
                '/parentId',
                $parentId
            );
        }

        if ($afterMenuItemId === $itemId) {
            throw $this->violation(
                'A menu item cannot be positioned after itself.',
                self::VIOLATION_MOVE_UNDER_ITSELF,
                '/afterMenuItemId',
                $afterMenuItemId
            );
        }

        if ($parentId !== null) {
            $this->assertNotDescendant($context, $itemId, $parentId);
        }

        if ($afterMenuItemId !== null) {
            $sibling = $this->requireItem($context, $afterMenuItemId);

            if ($sibling->getParentId() !== $parentId) {
                throw $this->violation(
                    \sprintf('The menu item %s is not a child of %s.', $afterMenuItemId, $parentId ?? 'root'),
                    self::VIOLATION_AFTER_NOT_SIBLING,
                    '/afterMenuItemId',
                    $afterMenuItemId
                );
            }
        }

        $this->menuItemRepository->update([
            ...$this->spliceOutOfLevel($context, $item),
            ...$this->takePositionInLevel($context, $item->getId(), $parentId, $afterMenuItemId),
            ['id' => $itemId, 'parentId' => $parentId, 'afterMenuItemId' => $afterMenuItemId],
        ], $context);
    }

    private function requireItem(Context $context, string $itemId): AdminMenuItemEntity
    {
        $item = $this->menuItemRepository
            ->search((new Criteria([$itemId]))->addAssociation('roles'), $context)
            ->getEntities()
            ->get($itemId);

        if (!$item instanceof AdminMenuItemEntity) {
            throw $this->violation(
                \sprintf('The menu item %s does not exist.', $itemId),
                self::VIOLATION_ITEM_NOT_FOUND,
                '/' . $itemId,
                $itemId
            );
        }

        return $item;
    }

    /**
     * The parent chain of the new parent must not contain the moved item, or the item
     * would become its own ancestor and disappear from the tree.
     */
    private function assertNotDescendant(Context $context, string $itemId, string $newParentId): void
    {
        $cursor = $newParentId;
        $visited = [];

        while ($cursor !== null) {
            if ($cursor === $itemId || isset($visited[$cursor])) {
                throw $this->violation(
                    \sprintf('A menu item cannot be moved below one of its own children (%s).', $newParentId),
                    self::VIOLATION_MOVE_UNDER_DESCENDANT,
                    '/parentId',
                    $newParentId
                );
            }

            $parent = $this->requireItem($context, $cursor);
            $cursor = $parent->getParentId();
            $visited[$parent->getId()] = true;
        }
    }

    /**
     * The follower of the moved item inherits its position, so the level the item leaves
     * stays a chain.
     *
     * @return list<array<string, mixed>>
     */
    private function spliceOutOfLevel(Context $context, AdminMenuItemEntity $item): array
    {
        $followers = $this->menuItemRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('afterMenuItemId', $item->getId()))
                ->setLimit(1),
            $context
        )->getEntities();

        $updates = [];

        foreach ($followers as $follower) {
            $updates[] = [
                'id' => $follower->getId(),
                'afterMenuItemId' => $item->getAfterMenuItemId(),
            ];
        }

        return $updates;
    }

    /**
     * The item takes the position after `$afterMenuItemId`: the sibling currently
     * positioned there becomes the follower of the item. Without a predecessor the item
     * leads the level, and the current head becomes its follower.
     *
     * @return list<array<string, mixed>>
     */
    private function takePositionInLevel(Context $context, string $itemId, ?string $parentId, ?string $afterMenuItemId): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parentId))
            ->setLimit(2);

        if ($afterMenuItemId !== null) {
            $criteria->addFilter(new EqualsFilter('afterMenuItemId', $afterMenuItemId));
        } else {
            // the head of the level is the sibling without a predecessor
            $criteria->addFilter(new EqualsFilter('afterMenuItemId', null));
        }

        $updates = [];

        foreach ($this->menuItemRepository->search($criteria, $context)->getEntities() as $sibling) {
            if ($sibling->getId() === $itemId) {
                continue;
            }

            $updates[] = [
                'id' => $sibling->getId(),
                'afterMenuItemId' => $itemId,
            ];
        }

        return $updates;
    }

    private function violation(string $message, string $code, string $propertyPath, mixed $invalidValue): WriteConstraintViolationException
    {
        return new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $propertyPath, $invalidValue, null, $code),
        ]));
    }
}
