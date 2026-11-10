/**
 * @sw-package discovery
 */
import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-category-list.html.twig';
import './sw-blog-category-list.scss';
import { createCategoryWorkspaceState, hasCategoryDraftChanges } from './category-workspace-state';
import { blogApi } from '../../service/blog.api.service';

type BlogCategory = EntitySchema.Entity<'blog_category'>;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        categoryId: { type: String, default: null },
    },

    data() {
        return createCategoryWorkspaceState(
            this.categoryId,
            typeof this.$route.query?.term === 'string' ? this.$route.query.term : '',
        );
    },

    computed: {
        isBusy(): boolean {
            return this.isSaving || this.isUpdatingAssignments || this.isDeleting || this.isTreeMutating || this.isLoading;
        },

        isNew(): boolean {
            return this.category !== null && !this.selectedId;
        },

        visibleEntries(): EntitySchema.Entity<'blog'>[] {
            const removed = new Set(this.removedAssignments.map((assignment) => assignment.blogId));
            const added = this.entriesPage === 1 ? this.addedEntries : [];
            return [...added, ...(this.entries ?? [])].filter((entry) => !removed.has(entry.id));
        },

        tabItems(): Array<{ name: string; label: string }> {
            return [
                { name: 'general', label: this.$t('sw-blog.category.tabGeneral') },
                ...(!this.isNew ? [{ name: 'content', label: this.$t('sw-blog.category.tabContent') }] : []),
                { name: 'layout', label: this.$t('sw-blog.category.tabLayout') },
                { name: 'seo', label: this.$t('sw-blog.category.tabSeo') },
            ];
        },

        categoryRepository(): RepositoryType<'blog_category'> {
            return this.repositoryFactory.create('blog_category');
        },

        blogRepository(): RepositoryType<'blog'> {
            return this.repositoryFactory.create('blog');
        },

        assignmentRepository(): RepositoryType<'blog_category_assignment'> {
            return this.repositoryFactory.create('blog_category_assignment');
        },

        entryOptionsCriteria(): CriteriaType {
            const criteria = new Shopwell.Data.Criteria(1, 25);
            criteria.addSorting(Shopwell.Data.Criteria.sort('title', 'ASC'));
            return criteria;
        },

        canEdit(): boolean {
            return this.acl.can(this.isNew ? 'blog.creator' : 'blog.editor');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        hasChildren(): boolean {
            return ((this.category as (BlogCategory & { childCount?: number }) | null)?.childCount ?? 0) > 0;
        },

        name(): string {
            return this.category?.name ?? '';
        },

        deleteMessage(): string {
            return this.$t('sw-blog.list.textDeleteCategory', { name: this.name });
        },

        entriesColumns(): Array<Record<string, unknown>> {
            return [
                { property: 'title', label: 'sw-blog.author.columnEntryTitle', primary: true },
                { property: 'type', label: 'sw-blog.list.columnType', width: '120px' },
                { property: 'publishedAt', label: 'sw-blog.author.columnEntryPublishedAt', width: '180px' },
                { property: 'updatedAt', label: 'sw-blog.list.columnUpdatedAt', width: '140px' },
            ];
        },
    },

    watch: {
        categoryId(value: string | null) {
            this.selectCategory(value);
        },
    },

    beforeRouteLeave(to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) {
        this.guardNavigation(next);
    },

    beforeRouteUpdate(to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) {
        this.guardNavigation(next);
    },

    created() {
        void this.loadCategory();
        void this.loadEntries();
    },

    methods: {
        onCreateCategory(placement?: { parentId: string | null; afterCategoryId: string | null }): void {
            if (!this.acl.can('blog.creator') || this.isBusy) return;
            if (!placement) {
                const navigation = this.$refs.categoryNavigation as { onOpenCreate: () => void };
                navigation.onOpenCreate();
                return;
            }
            this.requestNavigation(() => {
                const previousLanguage = Shopwell.Context.api.languageId;
                Shopwell.Store.get('context').resetLanguageToDefault();
                if (previousLanguage !== Shopwell.Context.api.languageId) this.navigationRevision += 1;
                this.resetEditor();
                this.selectedId = null;
                this.activeTab = 'general';
                this.category = this.categoryRepository.create(Shopwell.Context.api);
                this.category.name = '';
                this.category.slug = '';
                this.category.active = true;
                Object.assign(this.category, placement);
            });
        },

        resetEditor(): void {
            this.categoryRequest += 1;
            this.entriesRequest += 1;
            this.isLoading = false;
            this.isEntriesLoading = false;
            this.category = null;
            this.entries = null;
            this.assignmentsChanged = false;
            this.addedEntries = [];
            this.removedAssignments = [];
            this.entriesPage = 1;
            this.entriesTotal = 0;
        },

        selectCategory(id: string | null): void {
            this.resetEditor();
            this.selectedId = id;
            void this.loadCategory();
            void this.loadEntries();
        },

        async loadCategory(): Promise<boolean> {
            const requestId = ++this.categoryRequest;
            if (!this.selectedId) {
                this.category = null;
                return true;
            }
            this.isLoading = true;

            try {
                const categoryId = this.selectedId;
                const criteria = blogApi(this).categoryDetailCriteria(categoryId);
                const category = await this.categoryRepository.get(
                    this.selectedId as EntitySchema.BlogCategoryId,
                    Shopwell.Context.api,
                    criteria,
                );

                if (requestId !== this.categoryRequest || categoryId !== this.selectedId) {
                    return false;
                }
                this.category = category ?? null;
                if (this.category) this.category.active ??= true;
                return this.category !== null;
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
                return false;
            } finally {
                if (requestId === this.categoryRequest) {
                    this.isLoading = false;
                }
            }
        },

        async loadEntries(): Promise<void> {
            const requestId = ++this.entriesRequest;
            const categoryId = this.selectedId;
            if (!categoryId) {
                this.entries = null;
                this.entriesTotal = 0;
                return;
            }
            const criteria = blogApi(this).entriesOfCategoryCriteria(categoryId);
            criteria.setPage(this.entriesPage);
            criteria.setLimit(25);
            this.isEntriesLoading = true;
            try {
                const result = await this.blogRepository.search(criteria, Shopwell.Context.api);
                if (requestId !== this.entriesRequest || categoryId !== this.selectedId) {
                    return;
                }
                this.entriesTotal = result.total ?? 0;
                if (!result.length && this.entriesPage > 1) {
                    this.entriesPage = Math.max(1, Math.ceil(this.entriesTotal / 25));
                    await this.loadEntries();
                    return;
                }
                this.entries = result;
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                if (requestId === this.entriesRequest) {
                    this.isEntriesLoading = false;
                }
            }
        },

        onEntriesPageChange({ page }: { page: number }): void {
            this.entriesPage = page;
            void this.loadEntries();
        },

        validateCategory(): boolean {
            if (!this.category) return false;
            if (this.name.trim() === '') {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageTitleRequired') });

                return false;
            }

            if (this.isNew && !this.category.slug?.trim()) {
                this.category.slug = blogApi(this).slugFor(this.name, this.$t('sw-blog.list.defaultCategorySlug'));
            }

            if ((this.category.slug ?? '').trim() === '') {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSlugRequired') });

                return false;
            }

            return true;
        },

        async onSave(): Promise<boolean> {
            if (!this.category || this.isBusy || !this.canEdit || !this.validateCategory()) {
                return false;
            }

            this.isSaving = true;
            const category = this.category;
            const wasNew = this.isNew;
            const requestId = this.categoryRequest;
            try {
                await this.categoryRepository.save(category);
                if (category !== this.category || requestId !== this.categoryRequest) {
                    return false;
                }
                if (!(await this.saveRemovedAssignments(category, requestId))) {
                    return false;
                }

                if (wasNew) this.selectedId = category.id;

                if (!(await this.loadCategory())) {
                    return false;
                }
                this.addedEntries = [];
                this.assignmentsChanged = false;
                await this.loadEntries();
                this.navigationRevision += 1;
                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageSaveSuccess') });
                return true;
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async saveRemovedAssignments(category: BlogCategory, requestId: number): Promise<boolean> {
            for (const assignment of [...this.removedAssignments]) {
                if (category !== this.category || requestId !== this.categoryRequest) {
                    return false;
                }
                await this.assignmentRepository.delete(assignment.id, Shopwell.Context.api);
                if (category !== this.category || requestId !== this.categoryRequest) {
                    return false;
                }
                this.entries?.remove(assignment.blogId);
                this.addedEntries = this.addedEntries.filter((entry) => entry.id !== assignment.blogId);
                const pending = category.assignments?.find((item) => item.blogId === assignment.blogId);
                if (pending) {
                    category.assignments?.remove(pending.id);
                }
                this.removedAssignments = this.removedAssignments.filter((item) => item.id !== assignment.id);
            }
            return true;
        },

        onSelectCategory(id: string): void {
            if (this.isBusy || id === this.selectedId) return;
            this.requestNavigation(() => this.selectCategory(id));
        },

        guardNavigation(next: (allow?: boolean) => void): void {
            if (this.isBusy) {
                next(false);
                return;
            }
            if (!this.abortOnLanguageChange()) {
                next();
                return;
            }
            this.pendingNavigationIsRoute = true;
            this.pendingNavigation = () => next();
            this.cancelNavigation = () => next(false);
        },

        requestNavigation(navigate: () => void): void {
            if (this.isBusy) {
                return;
            }
            if (this.abortOnLanguageChange()) {
                this.pendingNavigationIsRoute = false;
                this.pendingNavigation = navigate;
                return;
            }
            navigate();
        },

        onCancelNavigation(): void {
            this.cancelNavigation?.();
            this.cancelNavigation = null;
            this.pendingNavigation = null;
        },

        async onDiscardNavigation(): Promise<void> {
            await this.completePendingNavigation(true);
        },

        async completePendingNavigation(discard: boolean): Promise<void> {
            if (this.isSaving || !this.pendingNavigation) {
                return;
            }
            const navigate = this.pendingNavigation;
            const cancel = this.cancelNavigation;
            const isRoute = this.pendingNavigationIsRoute;
            this.pendingNavigation = null;
            this.cancelNavigation = null;
            if (!isRoute && discard) {
                this.addedEntries = [];
                this.removedAssignments = [];
                this.assignmentsChanged = false;
                if (!(await this.loadCategory())) {
                    cancel?.();
                    return;
                }
                await this.loadEntries();
            }
            navigate();
        },

        async onSaveNavigation(): Promise<void> {
            if (await this.onSave()) {
                await this.completePendingNavigation(false);
            }
        },

        async onBeforeStructureChange(): Promise<boolean> {
            if (this.isBusy) {
                return false;
            }
            if (!this.abortOnLanguageChange()) {
                return true;
            }
            return new Promise<boolean>((resolve) => {
                this.pendingNavigationIsRoute = false;
                this.pendingNavigation = () => resolve(true);
                this.cancelNavigation = () => resolve(false);
            });
        },

        async onStructureChanged(): Promise<void> {
            if (!this.isNew) await this.loadCategory();
        },

        async findAssignment(
            blogId: EntitySchema.EntityKey<'blog'>,
        ): Promise<EntitySchema.Entity<'blog_category_assignment'> | null> {
            const criteria = new Shopwell.Data.Criteria(1, 1);
            criteria.addFilter(Shopwell.Data.Criteria.equals('blogCategoryId', this.selectedId));
            criteria.addFilter(Shopwell.Data.Criteria.equals('blogId', blogId));
            const result = await this.assignmentRepository.search(criteria, Shopwell.Context.api);
            return result[0] ?? null;
        },

        async onAddEntry(id: EntitySchema.EntityKey<'blog'> | null, entry?: EntitySchema.Entity<'blog'>): Promise<void> {
            if (
                !this.canEdit ||
                !this.category ||
                !id ||
                !entry ||
                this.isUpdatingAssignments ||
                this.addedEntries.some((item) => item.id === id)
            ) {
                return;
            }
            const removed = this.removedAssignments.find((assignment) => assignment.blogId === id);
            if (removed) {
                this.removedAssignments = this.removedAssignments.filter((assignment) => assignment.id !== removed.id);
                this.assignmentsChanged = this.addedEntries.length > 0 || this.removedAssignments.length > 0;
                return;
            }
            this.isUpdatingAssignments = true;
            const categoryId = this.selectedId;
            const categoryRequest = this.categoryRequest;
            try {
                if (
                    (await this.findAssignment(id)) ||
                    categoryId !== this.selectedId ||
                    categoryRequest !== this.categoryRequest
                ) {
                    return;
                }
                const assignment = this.assignmentRepository.create(Shopwell.Context.api);
                assignment.blogId = id;
                assignment.blogCategoryId = this.category.id;
                this.category.assignments ??= new Shopwell.Data.EntityCollection(
                    '/blog-category-assignment',
                    'blog_category_assignment',
                    Shopwell.Context.api,
                );
                this.category.assignments.add(assignment);
                this.addedEntries.push(entry);
                this.assignmentsChanged = true;
                if (this.entriesPage !== 1) {
                    this.entriesPage = 1;
                    await this.loadEntries();
                }
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isUpdatingAssignments = false;
            }
        },

        async onRemoveEntry(entry: EntitySchema.Entity<'blog'>): Promise<void> {
            if (
                !this.canEdit ||
                this.isUpdatingAssignments ||
                this.removedAssignments.some((assignment) => assignment.blogId === entry.id)
            ) {
                return;
            }
            this.isUpdatingAssignments = true;
            const categoryId = this.selectedId;
            const requestId = this.categoryRequest;
            try {
                const assignment = await this.findAssignment(entry.id);
                if (categoryId !== this.selectedId || requestId !== this.categoryRequest) {
                    return;
                }
                if (assignment) {
                    this.removedAssignments.push(assignment);
                    this.assignmentsChanged = true;
                    return;
                }
                // Only remove staged additions locally after confirming no persisted mapping remains.
                const added = this.addedEntries.find((item) => item.id === entry.id);
                if (added) {
                    const pending = this.category?.assignments?.find((item) => item.blogId === entry.id);
                    if (pending) {
                        this.category?.assignments?.remove(pending.id);
                    }
                    this.addedEntries = this.addedEntries.filter((item) => item.id !== entry.id);
                    this.assignmentsChanged = this.addedEntries.length > 0 || this.removedAssignments.length > 0;
                }
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isUpdatingAssignments = false;
            }
        },

        onOpenDeleteModal(): void {
            if (this.isBusy || this.hasChildren) {
                return;
            }
            this.showDeleteModal = true;
        },

        onDelete(): void {
            if (!this.canDelete || this.isBusy || this.hasChildren || this.isNew) {
                return;
            }
            this.showDeleteModal = false;
            this.isDeleting = true;
            const requestId = this.categoryRequest;

            void this.categoryRepository
                .delete(this.selectedId as EntitySchema.BlogCategoryId)
                .then(() => {
                    if (requestId !== this.categoryRequest) return;
                    this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageDeleteSuccess') });

                    this.selectCategory(null);
                    this.navigationRevision += 1;
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                })
                .finally(() => {
                    this.isDeleting = false;
                });
        },

        abortOnLanguageChange(): boolean {
            const category = this.category;
            return (
                this.isBusy ||
                this.assignmentsChanged ||
                Boolean(
                    category &&
                        (this.isNew ? hasCategoryDraftChanges(category) : this.categoryRepository.hasChanges(category)),
                )
            );
        },

        async saveOnLanguageChange(): Promise<void> {
            if (!(await this.onSave())) {
                throw new Error('Blog category save failed');
            }
        },

        onChangeLanguage(languageId: EntitySchema.EntityKey<'language'>): void {
            if (this.isBusy) {
                return;
            }
            Shopwell.Store.get('context').setApiLanguageId(languageId);
            this.addedEntries = [];
            this.removedAssignments = [];
            this.assignmentsChanged = false;
            void this.loadCategory();
            void this.loadEntries();
            this.navigationRevision += 1;
        },
    },
});
