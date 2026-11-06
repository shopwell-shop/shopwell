<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * One navigation row as the declarations describe it: the stored identity, the fields the
 * declaration owns and the place it takes among its siblings.
 *
 * The row is what the synchronization writes and what a re-run compares against. Keeping it
 * a value object (instead of a plain array) keeps the id derivation, the parent resolution
 * and the chain order in one place, so neither the installation nor the repair has to know
 * how an id is derived from an internal key.
 *
 * The label is not part of the row: the declaration carries a snippet key, the management
 * page stores what a person writes into the translation table. A key written here would be a
 * key the page shows and an overwrite waiting to happen on the next person who edits it.
 */
#[Package('fundamentals@framework')]
final class CoreMenuRow
{
    /**
     * @param array<string, mixed> $defaultMeta snapshot of the declaration this row was built from
     */
    public function __construct(
        public readonly string $id,
        public readonly string $internalKey,
        public readonly string $type,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly ?string $privilege,
        public readonly ?string $linkTarget,
        public readonly ?string $parentId,
        public readonly ?string $afterMenuItemId,
        public readonly array $defaultMeta,
        public readonly ?string $groupName = null,
    ) {
    }

    /**
     * The fields only the declaration may decide. Everything else about the row - whether it
     * is visible, which roles see it, where it was dragged to - belongs to whoever arranged
     * the navigation, so a release must not rewrite it.
     *
     * @return array<string, mixed>
     */
    public function declarationFields(): array
    {
        return [
            'internalKey' => $this->internalKey,
            'type' => $this->type,
            'icon' => $this->icon,
            'color' => $this->color,
            'privilege' => $this->privilege,
            'linkTarget' => $this->linkTarget,
            'groupName' => $this->groupName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function placement(): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parentId,
            'afterMenuItemId' => $this->afterMenuItemId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->declarationFields(),
            ...$this->placement(),
            'defaultMeta' => $this->defaultMeta,
        ];
    }
}
