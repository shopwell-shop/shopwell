import template from './sw-system-menu-tree.html.twig';
import './sw-system-menu-tree.scss';

/**
 * The share of a row's height that selects "drop before" or "drop after" instead of
 * "drop into the folder".
 */
const DROP_EDGE_RATIO = 0.25;

/**
 * Renders the stored navigation as an indented list and turns drag gestures into move
 * intents.
 *
 * The tree keeps no state of its own: which entries are open is the caller's decision (the
 * page starts with everything closed, because the navigation is long and its shape is what
 * an operator comes for), the label is already resolved, and a drop is reported instead of
 * applied - `AdminMenuConfigService::move()` repairs both affected levels, so the tree only
 * has to translate "below this row" into a `parentId` and an `afterMenuItemId`.
 *
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['toggle', 'edit', 'remove', 'move'],

    props: {
        items: {
            type: Array,
            required: true,
        },

        expandedIds: {
            type: Array,
            required: false,
            default: () => [],
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

        deletable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            draggedId: null,
            dropTargetId: null,
            dropZone: null,
        };
    },

    computed: {
        /**
         * The visible rows in render order. Rendering one flat list instead of nesting the
         * markup keeps the indentation and the drop targets uniform across levels.
         */
        rows() {
            const rows = [];

            const walk = (nodes, depth) => {
                nodes.forEach((node) => {
                    const hasChildren = node.children.length > 0;

                    rows.push({ item: node, depth, hasChildren });

                    if (hasChildren && this.isExpanded(node)) {
                        walk(node.children, depth + 1);
                    }
                });
            };

            walk(this.items, 0);

            return rows;
        },

        flatItems() {
            const flat = [];

            const walk = (nodes) => {
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
        isExpanded(item) {
            return this.expandedIds.includes(item.id);
        },

        hasChildren(item) {
            return item.children.length > 0;
        },

        toggle(item) {
            if (!this.hasChildren(item)) {
                return;
            }

            this.$emit('toggle', item);
        },

        onEdit(item) {
            this.$emit('edit', item);
        },

        onRemove(item) {
            this.$emit('remove', item);
        },

        iconOf(item) {
            if (item.icon) {
                return item.icon;
            }

            return item.type === 'folder' ? 'regular-folder' : 'regular-link';
        },

        typeLabel(item) {
            return `sw-system.menu.types.${item.type}`;
        },

        onDragStart(event, item) {
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

        onDragOver(event, item) {
            if (!this.canDropOn(item)) {
                return;
            }

            this.dropTargetId = item.id;
            this.dropZone = this.resolveZone(event, item);
        },

        onDragLeave(item) {
            if (this.dropTargetId === item.id) {
                this.dropTargetId = null;
                this.dropZone = null;
            }
        },

        onDrop(event, item) {
            if (!this.canDropOn(item)) {
                return;
            }

            const zone = this.resolveZone(event, item);
            const move = this.createMove(item, zone);

            this.resetDrag();

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

        isSortableItem(item) {
            return this.sortable && item.archived !== true;
        },

        /**
         * An item cannot become its own sibling or its own child, and it must not be dropped
         * below its own subtree - the server refuses that move, so the gesture is blocked
         * instead of producing a request that is guaranteed to fail.
         */
        canDropOn(item) {
            if (!this.draggedItem || item.id === this.draggedItem.id) {
                return false;
            }

            return this.descendantIds(this.draggedItem.id).includes(item.id) === false;
        },

        descendantIds(itemId) {
            const collected = [];

            const walk = (nodes) => {
                nodes.forEach((node) => {
                    collected.push(node.id);
                    walk(node.children);
                });
            };

            const start = this.flatItems.find((item) => item.id === itemId);
            walk(start ? start.children : []);

            return collected;
        },

        resolveZone(event, item) {
            const rect = event.currentTarget.getBoundingClientRect();
            const ratio = rect.height === 0 ? 0.5 : (event.clientY - rect.top) / rect.height;

            if (item.type !== 'folder') {
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
        createMove(item, zone) {
            if (zone === 'inside') {
                const children = item.children;

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

        rowClasses(item) {
            return {
                'is--hidden': item.active === false,
                'is--dragged': this.draggedId === item.id,
                'is--drop-before': this.dropTargetId === item.id && this.dropZone === 'before',
                'is--drop-after': this.dropTargetId === item.id && this.dropZone === 'after',
                'is--drop-inside': this.dropTargetId === item.id && this.dropZone === 'inside',
            };
        },
    },
};
