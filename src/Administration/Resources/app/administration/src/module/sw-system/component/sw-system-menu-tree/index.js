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
 * The tree keeps no ordering state of its own: it renders the levels it is given and
 * reports where an item was dropped. Rebuilding the sibling chain is the server's job -
 * `AdminMenuConfigService::move()` repairs both affected levels - so the tree only has to
 * translate "below this row" into a `parentId` and an `afterMenuItemId`.
 *
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Shopwell.Mixin.getByName('translate-with-fallback')],

    emits: ['select', 'move'],

    props: {
        items: {
            type: Array,
            required: true,
        },

        activeId: {
            type: String,
            required: false,
            default: null,
        },

        sortable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            collapsedIds: [],
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

                    if (hasChildren && !this.collapsedIds.includes(node.id)) {
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
        isActive(item) {
            return item.id === this.activeId;
        },

        isCollapsed(item) {
            return this.collapsedIds.includes(item.id);
        },

        hasChildren(item) {
            return item.children.length > 0;
        },

        toggle(item) {
            if (!this.hasChildren(item)) {
                return;
            }

            this.collapsedIds = this.isCollapsed(item)
                ? this.collapsedIds.filter((id) => id !== item.id)
                : [...this.collapsedIds, item.id];
        },

        expand(itemId) {
            this.collapsedIds = this.collapsedIds.filter((id) => id !== itemId);
        },

        select(item) {
            this.$emit('select', item.id);
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

        /**
         * Core rows store a snippet key as their title, entries created in the administration
         * store plain text. Both have to render as readable text, so a key is resolved and
         * anything unresolvable falls through unchanged.
         */
        rowLabel(item) {
            if (!item.title) {
                return item.internalKey || this.$t('sw-system.menu.labelUntitled');
            }

            return this.tWithFallback(item.title);
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
                'is--active': this.isActive(item),
                'is--hidden': item.active === false,
                'is--dragged': this.draggedId === item.id,
                'is--drop-before': this.dropTargetId === item.id && this.dropZone === 'before',
                'is--drop-after': this.dropTargetId === item.id && this.dropZone === 'after',
                'is--drop-inside': this.dropTargetId === item.id && this.dropZone === 'inside',
            };
        },

        onKeydown(event, item) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.select(item);
        },
    },
};
