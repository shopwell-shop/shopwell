/**
 * @sw-package fundamentals@framework
 */
import type { PropType } from 'vue';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';
import template from './sw-system-menu-tree.html.twig';
import './sw-system-menu-tree.scss';

/**
 * The share of a row's height that selects "drop before" or "drop after" instead of
 * "drop into the folder".
 */
const DROP_EDGE_RATIO = 0.25;

type RenderNode = MenuTreeNode & { label: string; groupLabel: string | null; children: RenderNode[] };
type TreeRow = { item: RenderNode; depth: number; hasChildren: boolean; groupHeader: string | null };
type DropZone = 'before' | 'after' | 'inside';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    emits: [
        'toggle',
        'select',
        'edit',
        'create-above',
        'create-below',
        'add-child',
        'remove',
        'move',
    ],

    props: {
        items: {
            type: Array as PropType<RenderNode[]>,
            required: true,
        },

        expandedIds: {
            type: Array as PropType<string[]>,
            required: false,
            default: () => [],
        },

        selectedId: {
            type: String,
            required: false,
            default: null,
        },

        sortable: {
            type: Boolean,
            required: false,
            default: false,
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },

        creatable: {
            type: Boolean,
            required: false,
            default: false,
        },

        deletable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            focusedId: null as string | null,
            draggedId: null as string | null,
            dropTargetId: null as string | null,
            dropZone: null as DropZone | null,
        };
    },

    computed: {
        /**
         * The visible rows in render order. Rendering one flat list instead of nesting the
         * markup keeps the indentation and the drop targets uniform across levels.
         */
        rows(): TreeRow[] {
            const rows: TreeRow[] = [];

            const walk = (nodes: RenderNode[], depth: number, grouped = false) => {
                const groups = new Map<string, RenderNode[]>();
                nodes.forEach((node) => {
                    const key = grouped ? (node.groupName ?? '') : '';
                    groups.set(key, [...(groups.get(key) ?? []), node]);
                });
                let previousGroup: string | null = null;
                const ordered = grouped ? [...groups.values()].flat() : nodes;
                ordered.forEach((node) => {
                    const group = node.groupName ?? '';
                    const groupHeader =
                        grouped && group !== previousGroup ? (node.groupLabel ?? this.$t('sw-system.menu.groupNone')) : null;
                    previousGroup = group;
                    const hasChildren = node.children.length > 0;

                    rows.push({ item: node, depth, hasChildren, groupHeader });

                    if (hasChildren && this.isExpanded(node)) {
                        walk(
                            node.children,
                            depth + 1,
                            node.internalKey === 'sw-settings' &&
                                node.children.some((child) => child.type !== 'group' && !!child.groupName),
                        );
                    }
                });
            };

            walk(this.items, 0);

            return rows;
        },

        focusableId() {
            const preferred = this.focusedId ?? this.selectedId;
            return this.rows.some((row) => row.item.id === preferred) ? preferred : this.rows[0]?.item.id;
        },

        flatItems() {
            const flat: RenderNode[] = [];

            const walk = (nodes: RenderNode[]): void => {
                nodes.forEach((node) => {
                    flat.push(node);
                    walk(node.children);
                });
            };

            walk(this.items);

            return flat;
        },

        draggedItem() {
            return this.flatItems.find((item) => item.id === this.draggedId) ?? null;
        },
    },

    methods: {
        focusRow(id: string | null | undefined) {
            if (!id) {
                return;
            }
            this.focusedId = id;
            void this.$nextTick(() => (this.$refs.rows as HTMLElement[])?.find((row) => row.dataset.id === id)?.focus());
        },

        onRowKeydown(event: KeyboardEvent, row: TreeRow) {
            if (event.target !== event.currentTarget) {
                return;
            }
            const index = this.rows.findIndex((entry) => entry.item.id === row.item.id);
            const { item } = row;
            switch (event.key) {
                case 'ArrowDown':
                    this.focusRow(this.rows[index + 1]?.item.id);
                    break;
                case 'ArrowUp':
                    this.focusRow(this.rows[index - 1]?.item.id);
                    break;
                case 'Home':
                    this.focusRow(this.rows[0]?.item.id);
                    break;
                case 'End':
                    this.focusRow(this.rows[this.rows.length - 1]?.item.id);
                    break;
                case 'ArrowRight':
                case 'ArrowLeft':
                    this.onHorizontalKey(event.key, row);
                    break;
                case 'Enter':
                case ' ':
                    this.onSelect(item);
                    break;
                default:
                    return;
            }
            event.preventDefault();
            event.stopPropagation();
        },

        onHorizontalKey(key: string, { item, hasChildren }: TreeRow) {
            if (key === 'ArrowRight') {
                if (hasChildren && !this.isExpanded(item)) this.toggle(item);
                else if (hasChildren) this.focusRow(item.children[0]?.id);
            } else if (hasChildren && this.isExpanded(item)) this.toggle(item);
            else this.focusRow(item.parentId);
        },

        isExpanded(item: RenderNode) {
            return this.expandedIds.includes(item.id);
        },

        hasChildren(item: RenderNode) {
            return item.children.length > 0;
        },

        toggle(item: RenderNode) {
            if (!this.hasChildren(item)) {
                return;
            }

            this.$emit('toggle', item);
        },

        onEdit(item: RenderNode) {
            this.$emit('edit', item);
        },

        onSelect(item: RenderNode) {
            this.focusedId = item.id;
            this.$emit('select', item);
        },

        onCreateAbove(item: RenderNode) {
            this.$emit('create-above', item);
        },

        onCreateBelow(item: RenderNode) {
            this.$emit('create-below', item);
        },

        onAddChild(item: RenderNode) {
            this.$emit('add-child', item);
        },

        /**
         * The row itself only shows the type icon, the lock and the inactive state - the
         * type wording, the source plugin and the group live in the tooltip instead of
         * crowding every row.
         */
        rowTooltip(item: RenderNode) {
            const parts = [this.$t(`sw-system.menu.types.${item.type}`)];

            if (item.pluginName) {
                parts.push(item.pluginName);
            }

            if (item.groupLabel) {
                parts.push(item.groupLabel);
            }

            return parts.join(' · ');
        },

        onRemove(item: RenderNode) {
            this.$emit('remove', item);
        },

        iconOf(item: RenderNode) {
            if (item.icon) {
                return item.icon;
            }

            return item.type === 'group'
                ? 'regular-layer-group'
                : item.type === 'folder'
                  ? 'regular-folder'
                  : 'regular-link';
        },

        typeLabel(item: RenderNode) {
            return `sw-system.menu.types.${item.type}`;
        },

        onDragStart(event: DragEvent, item: RenderNode) {
            if (!this.isSortableItem(item)) {
                event.preventDefault();

                return;
            }

            this.draggedId = item.id;
            this.dropTargetId = null;
            this.dropZone = null;

            if (event.dataTransfer) {
                // Firefox starts a drag only when the gesture carries data
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.id);
            }
        },

        onDragOver(event: DragEvent, item: RenderNode) {
            if (!this.canDropOn(item)) {
                return;
            }

            this.dropTargetId = item.id;
            this.dropZone = this.resolveZone(event, item);
        },

        onDragLeave(item: RenderNode) {
            if (this.dropTargetId === item.id) {
                this.dropTargetId = null;
                this.dropZone = null;
            }
        },

        onDrop(event: DragEvent, item: RenderNode) {
            if (!this.canDropOn(item)) {
                return;
            }

            const zone = this.resolveZone(event, item);
            const move = this.createMove(item, zone);

            const dragged = this.draggedItem;
            this.resetDrag();
            if (
                !dragged ||
                move.afterMenuItemId === dragged.id ||
                (move.parentId === dragged.parentId && move.afterMenuItemId === dragged.afterMenuItemId)
            )
                return;
            this.$emit('move', move);
        },

        onDragEnd() {
            this.resetDrag();
        },

        resetDrag() {
            this.draggedId = null;
            this.dropTargetId = null;
            this.dropZone = null;
        },

        isSortableItem(item: RenderNode) {
            return this.sortable && !item.archived;
        },

        /**
         * An item cannot become its own sibling or its own child, and it must not be dropped
         * below its own subtree - the server refuses that move, so the gesture is blocked
         * instead of producing a request that is guaranteed to fail.
         */
        canDropOn(item: RenderNode) {
            if (!this.sortable || item.archived || !this.draggedItem || item.id === this.draggedItem.id) {
                return false;
            }

            const parent = this.flatItems.find((node) => node.id === item.parentId);
            if (
                parent?.internalKey === 'sw-settings' &&
                this.draggedItem.parentId === item.parentId &&
                this.draggedItem.type !== 'group' &&
                item.type !== 'group' &&
                this.draggedItem.groupName !== item.groupName
            )
                return false;
            return !this.descendantIds(this.draggedItem.id).includes(item.id);
        },

        descendantIds(itemId: string) {
            const collected: string[] = [];

            const walk = (nodes: RenderNode[]): void => {
                nodes.forEach((node) => {
                    collected.push(node.id);
                    walk(node.children);
                });
            };

            const start = this.flatItems.find((item) => item.id === itemId);
            walk(start ? start.children : []);

            return collected;
        },

        canHaveChildren(item: RenderNode) {
            return ['folder', 'group'].includes(item.type) || item.internalKey === 'sw-settings';
        },

        resolveZone(event: DragEvent, item: RenderNode): DropZone {
            const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
            const ratio = rect.height === 0 ? 0.5 : (event.clientY - rect.top) / rect.height;

            if (!this.canHaveChildren(item)) {
                return ratio < 0.5 ? 'before' : 'after';
            }

            if (ratio < DROP_EDGE_RATIO) {
                return 'before';
            }

            if (ratio > 1 - DROP_EDGE_RATIO) {
                return 'after';
            }

            return 'inside';
        },

        /**
         * Translates the drop zone into the two identifiers the move endpoint expects.
         * Dropping inside a folder appends: the new item follows the last child, or leads
         * the level when the folder was empty.
         */
        createMove(item: RenderNode, zone: DropZone) {
            if (zone === 'inside') {
                const children = item.children.filter((child) => child.id !== this.draggedId);

                return {
                    id: this.draggedId,
                    parentId: item.id,
                    afterMenuItemId: children.length > 0 ? children[children.length - 1].id : null,
                };
            }

            return {
                id: this.draggedId,
                parentId: item.parentId,
                afterMenuItemId: zone === 'after' ? item.id : item.afterMenuItemId,
            };
        },

        rowClasses(item: RenderNode) {
            return {
                'is--hidden': !item.active,
                'is--selected': item.id === this.selectedId,
                'is--dragged': this.draggedId === item.id,
                'is--drop-before': this.dropTargetId === item.id && this.dropZone === 'before',
                'is--drop-after': this.dropTargetId === item.id && this.dropZone === 'after',
                'is--drop-inside': this.dropTargetId === item.id && this.dropZone === 'inside',
            };
        },
    },
});
