/**
 * @sw-package discovery
 */
import type { PropType } from 'vue';
import template from './tree.html.twig';

/** @private */
export type CategoryNode = {
    id: string;
    name: string;
    parentId: string | null;
    afterCategoryId: string | null;
    childCount: number;
    active: boolean;
    path: string | null;
};
/** @private */
export type TreeItem = {
    id: string;
    data: CategoryNode;
    parentId: string | null;
    children: TreeItem[];
    childCount: number;
    initialOpened: boolean;
    active?: boolean;
};
/** @private */
export type DropPreview = { targetId: string; position: 'before' | 'after' | 'inside' | 'invalid' };

/** @private */
export function isInvalidParent(items: CategoryNode[], id: string, parentId: string | null): boolean {
    const visited = new Set<string>();
    let current = parentId;
    while (current) {
        if (current === id || visited.has(current)) {
            return true;
        }
        visited.add(current);
        current = items.find((item) => item.id === current)?.parentId ?? null;
    }
    return false;
}

/** @private */
export function orderedSiblings(items: CategoryNode[], parentId: string | null): CategoryNode[] {
    const remaining = items.filter((item) => item.parentId === parentId);
    const result: CategoryNode[] = [];
    let previousId: string | null = null;
    while (remaining.length) {
        const nextIndex = remaining.findIndex((item) => item.afterCategoryId === previousId);
        const [next] = remaining.splice(nextIndex < 0 ? 0 : nextIndex, 1);
        result.push(next);
        previousId = next.id;
    }
    return result;
}

type TreeRow = { item: TreeItem; depth: number; hasChildren: boolean };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    emits: ['drag-start', 'drag-end'],
    props: {
        items: { type: Array as PropType<CategoryNode[]>, required: true },
        sortable: { type: Boolean, default: true },
        activeTreeItemId: { type: String, default: null },
        openSelectedPath: { type: Boolean, default: true },
        previewDrop: { type: Function as PropType<(preview: DropPreview | null) => void>, required: true },
        onChangeRoute: { type: Function as PropType<(item: TreeItem) => void>, required: true },
    },
    data() {
        return {
            expandedIds: [] as string[],
            focusedId: null as string | null,
            draggedId: null as string | null,
            dropPreview: null as DropPreview | null,
        };
    },
    computed: {
        rows(): TreeRow[] {
            const rows: TreeRow[] = [];
            const walk = (parentId: string | null, depth: number) => {
                orderedSiblings(this.items, parentId).forEach((data) => {
                    const children = this.items.filter((item) => item.parentId === data.id);
                    const item: TreeItem = {
                        id: data.id,
                        data,
                        parentId,
                        children: [],
                        childCount: children.length,
                        initialOpened: this.expandedIds.includes(data.id),
                    };
                    rows.push({ item, depth, hasChildren: children.length > 0 });
                    if (item.initialOpened) walk(data.id, depth + 1);
                });
            };
            walk(null, 0);
            return rows;
        },
        focusableId(): string | undefined {
            const preferred = this.focusedId ?? this.activeTreeItemId;
            return this.rows.some(({ item }) => item.id === preferred) ? preferred : this.rows[0]?.item.id;
        },
    },
    mounted() {
        if (this.openSelectedPath) this.openTreeById(this.activeTreeItemId);
    },
    methods: {
        openTreeById(id: string | null): void {
            let item = this.items.find((node) => node.id === id);
            const visited = new Set<string>();
            while (item && !visited.has(item.id)) {
                visited.add(item.id);
                if (!this.expandedIds.includes(item.id) && this.items.some((node) => node.parentId === item?.id)) {
                    this.expandedIds.push(item.id);
                }
                item = this.items.find((node) => node.id === item?.parentId);
            }
        },
        toggle(id: string): void {
            this.expandedIds = this.expandedIds.includes(id)
                ? this.expandedIds.filter((value) => value !== id)
                : [...this.expandedIds, id];
        },
        focusRow(id: string | null | undefined): void {
            if (!id) return;
            this.focusedId = id;
            void this.$nextTick(() => (this.$refs.rows as HTMLElement[])?.find((row) => row.dataset.itemId === id)?.focus());
        },
        onRowKeydown(event: KeyboardEvent, row: TreeRow): void {
            if (event.target !== event.currentTarget) return;
            const index = this.rows.findIndex(({ item }) => item.id === row.item.id);
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
                    this.focusRow(this.rows.at(-1)?.item.id);
                    break;
                case 'ArrowRight':
                case 'ArrowLeft':
                    this.onHorizontalKey(event.key, row);
                    break;
                case 'Enter':
                case ' ':
                    this.onChangeRoute(item);
                    break;
                default:
                    return;
            }
            event.preventDefault();
            event.stopPropagation();
        },
        onHorizontalKey(key: string, { item, hasChildren }: TreeRow): void {
            if (key === 'ArrowRight') {
                if (hasChildren && !this.expandedIds.includes(item.id)) this.toggle(item.id);
                else if (hasChildren)
                    this.focusRow(this.rows[this.rows.findIndex((row) => row.item.id === item.id) + 1]?.item.id);
            } else if (hasChildren && this.expandedIds.includes(item.id)) this.toggle(item.id);
            else this.focusRow(item.parentId);
        },
        onDragStart(event: DragEvent, item: TreeItem): void {
            if (!this.sortable) {
                event.preventDefault();
                return;
            }
            this.$emit('drag-start');
            this.draggedId = item.id;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.id);
            }
        },
        resolvePreview(event: DragEvent, item: TreeItem): DropPreview | null {
            if (!this.sortable || !this.draggedId) return null;
            if (isInvalidParent(this.items, this.draggedId, item.id)) return { targetId: item.id, position: 'invalid' };
            const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
            const ratio = rect.height ? (event.clientY - rect.top) / rect.height : 0;
            return { targetId: item.id, position: ratio < 0.25 ? 'before' : ratio > 0.75 ? 'after' : 'inside' };
        },
        onDragOver(event: DragEvent, item: TreeItem): void {
            this.dropPreview = this.resolvePreview(event, item);
            this.previewDrop(this.dropPreview);
            if (event.dataTransfer)
                event.dataTransfer.dropEffect = this.dropPreview?.position === 'invalid' ? 'none' : 'move';
        },
        onDragLeave(event: DragEvent): void {
            if ((event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) return;
            this.dropPreview = null;
            this.previewDrop(null);
        },
        onDrop(event: DragEvent, item: TreeItem): void {
            const preview = this.resolvePreview(event, item);
            const source = this.items.find((node) => node.id === this.draggedId);
            this.resetDrag();
            if (!source || !preview || preview.position === 'invalid') return;
            const parentId = preview.position === 'inside' ? item.id : item.parentId;
            const siblings = orderedSiblings(this.items, parentId).filter((node) => node.id !== source.id);
            const index = siblings.findIndex((node) => node.id === item.id);
            const afterCategoryId =
                preview.position === 'inside'
                    ? (siblings.at(-1)?.id ?? null)
                    : preview.position === 'after'
                      ? item.id
                      : (siblings[index - 1]?.id ?? null);
            if (source.parentId === parentId && source.afterCategoryId === afterCategoryId) return;
            this.$emit('drag-end', { draggedItem: { data: { ...source, parentId, afterCategoryId } } });
        },
        resetDrag(): void {
            this.draggedId = null;
            this.dropPreview = null;
            this.previewDrop(null);
        },
        rowClasses(id: string): Record<string, boolean> {
            return {
                'is--selected': id === this.activeTreeItemId,
                'is--dragged': id === this.draggedId,
                [`is--drop-${this.dropPreview?.position}`]: id === this.dropPreview?.targetId,
            };
        },
    },
});
