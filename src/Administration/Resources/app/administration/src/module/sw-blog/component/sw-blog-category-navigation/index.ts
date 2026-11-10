/**
 * @sw-package discovery
 */
import type { PropType } from 'vue';
import template from './sw-blog-category-navigation.html.twig';
import './sw-blog-category-navigation.scss';
import { blogApi } from '../../service/blog.api.service';
import { isInvalidParent, orderedSiblings } from './tree';
import type { CategoryNode, TreeItem, DropPreview } from './tree';

const { Criteria } = Shopwell.Data;
type Category = EntitySchema.Entity<'blog_category'> & CategoryNode;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl', 'blogApiService'],
    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        selectedId: { type: String, default: null },
        searchTerm: { type: String, default: '' },
        refreshKey: { type: Number, default: 0 },
        disabled: { type: Boolean, default: false },
        beforeStructureChange: { type: Function as PropType<() => Promise<boolean>>, default: () => Promise.resolve(true) },
    },

    emits: [
        'select',
        'create',
        'structure-change',
        'structure-busy',
        'selection-path',
        'clear-search',
    ],

    data() {
        return {
            categories: [] as Category[],
            term: '',
            total: 0,
            isLoading: false,
            isMutating: false,
            requestId: 0,
            treeRevision: 0,
            expandedIds: [] as string[],
            openSelectedPath: true,
            dropPreview: null as DropPreview | null,
        };
    },

    computed: {
        selectedPath(): string {
            return this.selectedId ? this.categoryPath(this.selectedId) : '';
        },

        categoryRepository() {
            return this.repositoryFactory.create('blog_category');
        },
        canCreate(): boolean {
            return this.acl.can('blog.creator');
        },
        canMove(): boolean {
            return this.acl.can('blog.editor') && !this.disabled && !this.isMutating && !this.isLoading;
        },
        sortable(): boolean {
            return this.canMove && !this.term.trim();
        },
        isBusy(): boolean {
            return this.disabled || this.isMutating;
        },

        matchingIds(): Set<string> {
            const term = this.term.trim().toLocaleLowerCase();
            return new Set(
                this.categories
                    .filter((category) => this.categoryName(category).toLocaleLowerCase().includes(term))
                    .map((category) => category.id),
            );
        },

        treeCategories(): CategoryNode[] {
            const visible = new Set<string>();
            this.matchingIds.forEach((id) => {
                let current: string | null = id;
                while (current && !visible.has(current)) {
                    visible.add(current);
                    current = this.categories.find((category) => category.id === current)?.parentId ?? null;
                }
            });
            return this.categories
                .filter((category) => visible.has(category.id))
                .map((category) => ({
                    id: category.id,
                    name: this.categoryName(category),
                    parentId: category.parentId ?? null,
                    afterCategoryId: category.afterCategoryId ?? null,
                    childCount: this.categories.filter((child) => visible.has(child.id) && child.parentId === category.id)
                        .length,
                    active: category.active,
                    path: category.path ?? null,
                }));
        },

        dropMessage(): string {
            if (!this.dropPreview) {
                return '';
            }
            const key = { before: 'dropBefore', after: 'dropAfter', inside: 'dropInside', invalid: 'dropInvalid' }[
                this.dropPreview.position
            ];
            return this.$t(`sw-blog.category.${key}`, { name: this.categoryPath(this.dropPreview.targetId) });
        },
    },

    watch: {
        searchTerm: {
            immediate: true,
            handler(value: string) {
                this.term = value;
            },
        },
        selectedPath: {
            immediate: true,
            handler(path: string) {
                this.$emit('selection-path', path);
            },
        },
        refreshKey() {
            void this.loadCategories();
        },
        selectedId() {
            this.openSelectedPath = true;
            void this.$nextTick(() => this.openTreePaths());
        },
        term(_value: string, previous: string) {
            this.captureExpandedIds(previous);
            this.resetTree();
        },
    },

    created() {
        void this.loadCategories();
    },

    methods: {
        categoryName(category: EntitySchema.Entity<'blog_category'>): string {
            return category.translated?.name || category.name || '';
        },

        categoryPath(id: string): string {
            const names: string[] = [];
            const seen = new Set<string>();
            let current: string | null = id;
            while (current && !seen.has(current)) {
                seen.add(current);
                const category = this.categories.find((entry) => entry.id === current);
                if (!category) {
                    break;
                }
                names.unshift(this.categoryName(category));
                current = category.parentId;
            }
            return names.join(' / ');
        },

        async loadCategories(): Promise<void> {
            const requestId = ++this.requestId;
            this.captureExpandedIds();
            this.isLoading = true;
            const loaded: Category[] = [];
            try {
                let page = 1;
                let hasMore = true;
                while (hasMore) {
                    const criteria = new Criteria(page, 100);
                    criteria.addSorting(Criteria.sort('id', 'ASC'));
                    const result = await this.categoryRepository.search(criteria, Shopwell.Context.api);
                    if (requestId !== this.requestId) {
                        return;
                    }
                    loaded.push(...result.map((category) => category as Category));
                    hasMore =
                        result.length > 0 && (result.total === null ? result.length === 100 : loaded.length < result.total);
                    page += 1;
                }
                this.categories = loaded;
                this.total = loaded.length;
                this.resetTree();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                if (requestId === this.requestId) {
                    this.isLoading = false;
                }
            }
        },

        captureExpandedIds(term?: string): void {
            term ??= this.term;
            if (term.trim()) return;
            const root = this.$el as HTMLElement | undefined;
            this.expandedIds = Array.from(
                root?.querySelectorAll<HTMLElement>('.sw-blog-category-tree__item[aria-expanded="true"]') ?? [],
            )
                .map((element) => element.dataset.itemId)
                .filter((id): id is string => Boolean(id));
        },

        resetTree(): void {
            this.categories = [...this.categories];
            this.treeRevision += 1;
            void this.$nextTick(() => this.openTreePaths());
        },

        openTreePaths(): void {
            const tree = this.$refs.categoryTree as { openTreeById: (id: string) => void } | undefined;
            const ids = this.term.trim()
                ? [...this.matchingIds]
                : [...this.expandedIds, ...(this.openSelectedPath && this.selectedId ? [this.selectedId] : [])];
            ids.forEach((id) => tree?.openTreeById(id));
        },

        onExpandAll(): void {
            this.expandedIds = this.categories.filter((category) => category.childCount > 0).map((category) => category.id);
            this.resetTree();
        },
        onCollapseAll(): void {
            this.openSelectedPath = false;
            this.expandedIds = [];
            this.resetTree();
        },

        onSelect(item: TreeItem): void {
            if (!this.isBusy) {
                this.$emit('select', item.id);
            }
        },
        onDropPreview(preview: DropPreview | null): void {
            this.dropPreview = preview;
        },
        dropClass(id: string): string {
            return this.dropPreview?.targetId === id ? `is--drop-${this.dropPreview.position}` : '';
        },

        async onTreeDrop({ draggedItem }: { draggedItem: TreeItem }): Promise<void> {
            const { id, parentId, afterCategoryId } = draggedItem.data;
            this.resetTree();
            await this.move(id, parentId, afterCategoryId);
        },

        canMoveSibling(id: string, direction: number): boolean {
            const category = this.categories.find((item) => item.id === id);
            if (!category || !this.canMove) {
                return false;
            }
            const siblings = orderedSiblings(this.categories, category.parentId);
            const index = siblings.findIndex((item) => item.id === id);
            return index + direction >= 0 && index + direction < siblings.length;
        },

        async onMoveSibling(id: string, direction: number): Promise<void> {
            if (!this.canMoveSibling(id, direction)) {
                return;
            }
            const category = this.categories.find((item) => item.id === id);
            if (!category) {
                return;
            }
            const siblings = orderedSiblings(this.categories, category.parentId);
            const index = siblings.findIndex((item) => item.id === id);
            const afterId = direction < 0 ? (siblings[index - 2]?.id ?? null) : siblings[index + 1].id;
            await this.move(id, category.parentId, afterId);
        },

        async onMoveRoot(id: string): Promise<void> {
            await this.move(
                id,
                null,
                orderedSiblings(this.categories, null)
                    .filter((item) => item.id !== id)
                    .at(-1)?.id ?? null,
            );
        },
        async move(id: string, parentId: string | null, afterId: string | null): Promise<void> {
            if (!this.canMove || isInvalidParent(this.categories, id, parentId) || afterId === id) {
                return;
            }
            this.captureExpandedIds();
            this.isMutating = true;
            try {
                if (!(await this.beforeStructureChange()) || this.disabled) {
                    return;
                }
                this.$emit('structure-busy', true);
                await blogApi(this).moveCategory(id, parentId, afterId);
                await this.loadCategories();
                this.$emit('structure-change');
            } catch {
                this.resetTree();
                this.createNotificationError({ message: this.$t('sw-blog.category.moveError') });
            } finally {
                this.isMutating = false;
                this.$emit('structure-busy', false);
            }
        },

        onOpenCreate(parentId: string | null = null): void {
            if (!this.canCreate || this.isBusy) {
                return;
            }
            this.$emit('create', {
                parentId,
                afterCategoryId: orderedSiblings(this.categories, parentId).at(-1)?.id ?? null,
            });
        },
    },
});
