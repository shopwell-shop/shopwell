<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\CoreMenuRow;

/**
 * Turns the navigation declarations into the rows they describe.
 *
 * Two things are derived here, and nowhere else:
 *
 * - The identity. A core row carries no `plugin_id`, so `uniq.admin_menu_item.plugin_id__internal_key`
 *   does not apply to it - MySQL lets any number of rows share a NULL column. Deriving the id
 *   from the internal key keeps two writers that seed the same empty table at the same time
 *   from creating the row twice: both write the same primary key and the second write updates.
 * - The place. A declaration knows a `position` among its siblings, the table only knows the
 *   `after_menu_item_id` chain; materializing one into the other is done level by level, so
 *   the rows come out parents-first and a seeder can insert them without meeting a foreign
 *   key that points at a row it has not written yet.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class CoreMenuRowsBuilder
{
    private const ID_NAMESPACE = 'admin-menu:';

    /**
     * @param list<AdminMenuDefinitionStruct> $declarations
     * @param array<string, string> $anchors internal key to id of rows the caller must not re-place
     *                                       (a customized item keeps its position but still occupies the chain for the entry
     *                                       that follows it)
     * @param array<string, string> $storedIds existing identities retained when an internal key changes
     *
     * @return list<CoreMenuRow> parents before their children, siblings in declared order
     */
    public function build(array $declarations, array $anchors = [], array $storedIds = []): array
    {
        $ids = [];

        foreach ($declarations as $declaration) {
            $ids[$declaration->internalKey] = $anchors[$declaration->internalKey]
                ?? $storedIds[$declaration->internalKey]
                ?? $this->idFor($declaration->internalKey);
        }

        $byParent = [];

        foreach ($declarations as $declaration) {
            // a declaration naming a parent no provider declares is a broken declaration; the
            // entry belongs to the top level then instead of disappearing from the tree
            $parentKey = $declaration->parent !== null && isset($ids[$declaration->parent])
                ? $declaration->parent
                : '';

            $byParent[$parentKey][] = $declaration;
        }

        $rows = [];
        $placed = [];
        $level = [''];

        while ($level !== []) {
            $nextLevel = [];

            foreach ($level as $parentKey) {
                if (!isset($byParent[$parentKey])) {
                    continue;
                }

                $predecessor = null;

                foreach ($this->sortByPosition($byParent[$parentKey]) as $declaration) {
                    $internalKey = $declaration->internalKey;
                    $placed[$internalKey] = true;
                    $nextLevel[$internalKey] = true;

                    if (isset($anchors[$internalKey])) {
                        // the row keeps the place an administrator gave it, but the entry that
                        // follows has to be chained behind it regardless
                        $predecessor = $ids[$internalKey];

                        continue;
                    }

                    $rows[] = $this->createRow($declaration, $parentKey, $predecessor, $ids[$internalKey], $ids);
                    $predecessor = $ids[$internalKey];
                }
            }

            $level = array_keys($nextLevel);
        }

        return [...$rows, ...$this->orphanedRows($declarations, $placed, $ids)];
    }

    public function idFor(string $internalKey): string
    {
        // Uuid::fromStringToHex is the framework's md5-of-a-string to id conversion, so the
        // derivation stays the same one the rest of the platform uses
        return Uuid::fromStringToHex(self::ID_NAMESPACE . $internalKey);
    }

    /**
     * Declarations that form a cycle among their parents are not reachable from the top level.
     * Dropping them would remove menu entries from an installation because of a broken
     * declaration, so they are written as top level entries instead.
     *
     * @param list<AdminMenuDefinitionStruct> $declarations
     * @param array<string, true> $placed
     * @param array<string, string> $ids
     *
     * @return list<CoreMenuRow>
     */
    private function orphanedRows(array $declarations, array $placed, array $ids): array
    {
        $rows = [];

        foreach ($declarations as $declaration) {
            if (isset($placed[$declaration->internalKey])) {
                continue;
            }

            $rows[] = $this->createRow($declaration, '', null, $ids[$declaration->internalKey], $ids);
        }

        return $rows;
    }

    /**
     * @param array<string, string> $ids
     */
    private function createRow(
        AdminMenuDefinitionStruct $declaration,
        string $parentKey,
        ?string $predecessor,
        string $id,
        array $ids,
    ): CoreMenuRow {
        return new CoreMenuRow(
            id: $id,
            internalKey: $declaration->internalKey,
            type: $declaration->type,
            icon: $declaration->icon,
            color: $declaration->color,
            privilege: $declaration->privilege,
            linkTarget: $declaration->linkTarget,
            parentId: $parentKey === '' ? null : $ids[$parentKey],
            afterMenuItemId: $predecessor,
            defaultMeta: $declaration->jsonSerialize(),
            groupName: $declaration->groupName,
        );
    }

    /**
     * @param list<AdminMenuDefinitionStruct> $declarations
     *
     * @return list<AdminMenuDefinitionStruct>
     */
    private function sortByPosition(array $declarations): array
    {
        usort(
            $declarations,
            static fn (AdminMenuDefinitionStruct $a, AdminMenuDefinitionStruct $b): int => ($a->position ?? \PHP_INT_MAX) <=> ($b->position ?? \PHP_INT_MAX)
        );

        return $declarations;
    }
}
