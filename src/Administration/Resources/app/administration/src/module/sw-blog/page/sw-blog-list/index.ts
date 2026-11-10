/**
 * @sw-package discovery
 */
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-list.html.twig';
import './sw-blog-list.scss';
import type { BlogStatus } from '../../service/blog.api.service';
import { blogApi } from '../../service/blog.api.service';
import { getBlogTypeDefinition, registeredBlogTypes } from '../../service/blog-type.registry';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type Blog = EntitySchema.Entity<'blog'>;

type DeleteTarget = {
    id: EntitySchema.EntityKey<'blog'>;
    name: string | null;
};

type FilterOption = { id: string; name: string };

type FilterDefinition = {
    property: string;
    type?: string;
    label: string;
    placeholder: string;
    options?: FilterOption[];
    valueProperty?: string;
    labelProperty?: string;
};

interface FilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The stored filter set of this grid. Declared once, because the sidebar panel renders the defaults in
 * this order and the stored selection refers to the names.
 */
const DEFAULT_FILTERS: string[] = [
    'status-filter',
    'type-filter',
    'category-filter',
    'author-filter',
    'tag-filter',
];

/**
 * The filter factory is injected but not part of the typed service container, so it is read through
 * the component instance instead of widening the global type for one page.
 */
function blogFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'blogApiService',
        'filterFactory',
    ],
    mixins: [Mixin.getByName('listing')],

    data(): {
        blogs: EntityCollection<'blog'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        storeKey: string;
        defaultFilters: string[];
        activeFilterNumber: number;
        presetAuthorId: string | null;
        presetCategoryId: string | null;
        deleteTarget: DeleteTarget | null;
    } {
        return {
            blogs: null,
            isLoading: false,
            sortBy: 'publishedAt',
            sortDirection: 'DESC',
            storeKey: 'grid.filter.blog',
            defaultFilters: DEFAULT_FILTERS,
            activeFilterNumber: 0,
            presetAuthorId: null,
            presetCategoryId: null,
            deleteTarget: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        blogRepository(): RepositoryType<'blog'> {
            return this.repositoryFactory.create('blog');
        },

        /**
         * The columns in the order an editor reads them: what it looks like, what it is called, where
         * it is filed, whether it is live, and when it was touched last.
         */
        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'coverMediaId',
                    label: 'sw-blog.list.columnCover',
                    width: '80px',
                },
                {
                    property: 'title',
                    label: 'sw-blog.list.columnTitle',
                    primary: true,
                    routerLink: 'sw.blog.detail',
                    allowResize: true,
                },
                {
                    property: 'type',
                    label: 'sw-blog.list.columnType',
                    width: '110px',
                    sortable: false,
                },
                {
                    property: 'categories',
                    label: 'sw-blog.list.columnCategories',
                    allowResize: true,
                },
                {
                    property: 'status',
                    label: 'sw-blog.list.columnStatus',
                    width: '140px',
                    sortable: false,
                },
                {
                    property: 'updatedAt',
                    label: 'sw-blog.list.columnUpdatedAt',
                    width: '160px',
                },
            ];
        },

        typeFilterOptions(): FilterOption[] {
            return registeredBlogTypes().map((type) => {
                return { id: type, name: this.typeLabel(type) };
            });
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'status-filter': {
                    property: 'active',
                    type: 'multi-select-filter',
                    label: this.$t('sw-blog.list.filterStatus'),
                    placeholder: this.$t('sw-blog.list.textAllStatuses'),
                    options: ['draft', 'published', 'scheduled'].map((status) => ({
                        id: status,
                        name: this.$t(`sw-blog.status.${status}`),
                    })),
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
                'type-filter': {
                    property: 'type',
                    type: 'multi-select-filter',
                    label: this.$t('sw-blog.list.filterType'),
                    placeholder: this.$t('sw-blog.list.textAllTypes'),
                    options: this.typeFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
                'category-filter': {
                    property: 'categories.blogCategory',
                    label: this.$t('sw-blog.list.filterCategory'),
                    placeholder: this.$t('sw-blog.list.textAllCategories'),
                },
                'author-filter': {
                    property: 'author',
                    label: this.$t('sw-blog.list.filterAuthor'),
                    placeholder: this.$t('sw-blog.list.textAllAuthors'),
                },
                'tag-filter': {
                    property: 'tags',
                    label: this.$t('sw-blog.list.filterTags'),
                    placeholder: this.$t('sw-blog.list.textAllTags'),
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return blogFilterFactory(this).create('blog', this.listFilterOptions);
        },

        canView(): boolean {
            return this.acl.can('blog.viewer');
        },

        canCreate(): boolean {
            return this.acl.can('blog.creator');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        deleteName(): string {
            return this.deleteTarget?.name ?? '';
        },

        deleteMessage(): string {
            return this.$t('sw-blog.list.textDeleteBlog', { name: this.deleteName });
        },

        /**
         * A narrowed list that comes back empty must not read like an empty shop: the page says which
         * of the two it is.
         */
        hasActiveSearchOrFilter(): boolean {
            return (
                this.activeFilterNumber > 0 ||
                Boolean(this.term) ||
                this.presetAuthorId !== null ||
                this.presetCategoryId !== null
            );
        },

        /**
         * The ids the page was opened with, as chips: "author: Grace" tells the merchant why the list
         * is shorter than the shop, which a silent filter does not.
         */
        presetChips(): Array<{ type: string; label: string }> {
            const chips: Array<{ type: string; label: string }> = [];

            if (this.presetAuthorId !== null) {
                const name = this.presetAuthorId;

                chips.push({ type: 'author', label: `${this.$t('sw-blog.list.filterAuthor')}: ${name}` });
            }

            if (this.presetCategoryId !== null) {
                const name = this.presetCategoryId;

                chips.push({ type: 'category', label: `${this.$t('sw-blog.list.filterCategory')}: ${name}` });
            }

            return chips;
        },
    },

    created() {
        this.applyRouteQuery();

        void this.getList();
    },

    methods: {
        /**
         * The criteria the panel merges its filters into.
         *
         * Built on every search instead of being a computed: the listing mixin fires its own
         * `getList` next to the one of this page, and one cached criteria would collect the same
         * preset filter twice - once per call - until the list answers a question nobody asked.
         *
         * Nothing is filtered here: a filter that is written twice - once by the page, once by the
         * stored selection - can not be cleared by the merchant.
         */
        buildCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('author');
            criteria.addAssociation('categories.blogCategory');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'publishedAt', this.sortDirection));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        /**
         * The entry list is also the destination of the author and the category page: they open it
         * with the id they want to narrow to, instead of asking the merchant to find the filter.
         */
        applyRouteQuery(): void {
            const query = (this.$route as { query?: Record<string, unknown> }).query ?? {};

            this.presetAuthorId = this.queryValue(query.authorId);
            this.presetCategoryId = this.queryValue(query.categoryId);
        },

        queryValue(value: unknown): string | null {
            return typeof value === 'string' && value !== '' ? value : null;
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as {
                mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
            };

            // the service is async - without the await the criteria is a pending promise and reading
            // its filters throws, which leaves the grid permanently empty
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.buildCriteria());

            if (this.presetAuthorId) {
                criteria.addFilter(Criteria.equals('authorId', this.presetAuthorId));
            }

            if (this.presetCategoryId) {
                criteria.addFilter(Criteria.equals('categories.blogCategoryId', this.presetCategoryId));
            }

            // Stored status values use the native multi-select UI; resolve the relative publication
            // date on each search so a scheduled entry moves to published without stale criteria.
            criteria.filters = criteria.filters.map((filter) => {
                if (filter.type !== 'equalsAny' || filter.field !== 'active') {
                    return filter;
                }

                const statuses = filter.value
                    .split('|')
                    .filter((status): status is BlogStatus => ['draft', 'published', 'scheduled'].includes(status));

                if (statuses.length === 0) {
                    return filter;
                }

                return Criteria.multi(
                    'OR',
                    statuses.map((status) => {
                        return Criteria.multi('AND', blogApi(this).listCriteria(1, 1, { status }).filters);
                    }),
                );
            });

            this.activeFilterNumber = criteria.filters.length;

            return this.blogRepository
                .search(criteria)
                .then((result) => {
                    this.total = result.total ?? 0;
                    this.blogs = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * The panel hands the criteria it built back, so the next search uses exactly the filters the
         * merchant sees - no second derivation that could disagree with it.
         *
         * The filters are parked until the search reads them instead of being written into a criteria
         * here: the search builds a fresh criteria, and a value written into the old one would be
         * lost between the two calls the listing mixin and this page fire.
         */
        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.activeFilterNumber = filters.length;

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        onCreate(type: string, closeMenu?: () => void): void {
            if (!this.canCreate) {
                return;
            }

            closeMenu?.();
            void this.$router.push({ name: 'sw.blog.create', query: { type } });
        },

        /**
         * The label of one type, from the snippet when the type has one, otherwise the raw value -
         * the same fallback the filter dropdown uses, so a plugin type never renders as an empty
         * badge.
         */
        typeLabel(type: string | null | undefined): string {
            if (typeof type !== 'string' || type === '') {
                return '';
            }

            const label = getBlogTypeDefinition(type)?.label;
            const key = label ?? blogApi(this).typeLabelKey(type);

            return this.$te(key) ? this.$t(key) : (label ?? type);
        },

        onDelete(blog: Blog): void {
            this.deleteTarget = { id: blog.id, name: blog.title };
        },

        onCloseDeleteModal(): void {
            this.deleteTarget = null;
        },

        onConfirmDelete(): void {
            if (!this.deleteTarget) {
                return;
            }

            const { id } = this.deleteTarget;

            this.deleteTarget = null;

            void this.blogRepository.delete(id).then(() => this.getList());
        },

        /**
         * The two categories of a row as badges, capped at two: a comma separated string grows the
         * column with every category an entry is filed under.
         */
        categoryNamesOf(blog: Blog): string[] {
            const assignments = blog.categories ?? [];

            return assignments
                .map((assignment) => assignment.blogCategory?.name)
                .filter((name): name is string => typeof name === 'string' && name !== '');
        },

        /**
         * Clears the narrowed list: the stored filters, the search term and the id the page was
         * opened with. Any of the three can be why the table is empty.
         */
        onResetFilters(): void {
            this.term = '';
            this.presetAuthorId = null;
            this.presetCategoryId = null;
            this.page = 1;

            const panel = this.$refs.filterPanel as { resetAll?: () => void } | undefined;

            panel?.resetAll?.();

            void this.getList();
        },
    },
});
