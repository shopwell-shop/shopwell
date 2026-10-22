/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-list.html.twig';
import './sw-blog-list.scss';
import { blogApi } from '../../service/blog.api.service';
import { registeredBlogTypes } from '../../service/blog-type.registry';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type Blog = EntitySchema.Entity<'blog'>;
type BlogCategory = EntitySchema.Entity<'blog_category'>;
type BlogAuthor = EntitySchema.Entity<'blog_author'>;

type DeleteTarget = {
    id: EntitySchema.EntityKey<'blog'>;
    name: string | null;
};

type FilterOption = { id: string; name: string };

type FilterDefinition = {
    property: string;
    type: string;
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
const DEFAULT_FILTERS: string[] = ['type-filter', 'category-filter', 'author-filter'];

/**
 * The filter factory is injected but not part of the typed service container, so it is read through
 * the component instance instead of widening the global type for one page.
 */
function blogFilterFactory(vm: unknown): FilterFactory {
    return (vm as { filterFactory: FilterFactory }).filterFactory;
}

/**
 * Every entry of the blog.
 *
 * The list is the answer to "what does the shop publish, and when", so the state of an entry is
 * derived from its publication date instead of read from a column - the entity has no `active` flag,
 * and the same derivation is used by the badge of the detail page. A merchant therefore never sees a
 * row marked as published that the storefront hides.
 *
 * The filters live in the sidebar panel like they do on every other list: type, category and author
 * narrow the table, the state is a column and not a filter, because "published" is derived and a
 * derived state can not be a stored filter value.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        blogs: EntityCollection<'blog'> | null;
        categories: BlogCategory[];
        authors: BlogAuthor[];
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        storeKey: string;
        defaultFilters: string[];
        activeFilterNumber: number;
        pendingCommentCount: number;
        presetAuthorId: string | null;
        presetCategoryId: string | null;
        deleteTarget: DeleteTarget | null;
    } {
        return {
            blogs: null,
            categories: [],
            authors: [],
            isLoading: false,
            sortBy: 'publishedAt',
            sortDirection: 'DESC',
            storeKey: 'grid.filter.blog',
            defaultFilters: DEFAULT_FILTERS,
            activeFilterNumber: 0,
            pendingCommentCount: 0,
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

        categoryRepository(): RepositoryType<'blog_category'> {
            return this.repositoryFactory.create('blog_category');
        },

        authorRepository(): RepositoryType<'blog_author'> {
            return this.repositoryFactory.create('blog_author');
        },

        commentRepository(): RepositoryType<'blog_comment'> {
            return this.repositoryFactory.create('blog_comment');
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
                    inlineEdit: 'string',
                    allowResize: true,
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
                const key = blogApi(this).typeLabelKey(type);

                return { id: type, name: this.$te(key) ? this.$t(key) : type };
            });
        },

        categoryFilterOptions(): FilterOption[] {
            return this.categories.map((category) => {
                return { id: category.id, name: category.name ?? category.id };
            });
        },

        authorFilterOptions(): FilterOption[] {
            return this.authors.map((author) => {
                return { id: author.id, name: author.name };
            });
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
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
                    property: 'categories.blogCategoryId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-blog.list.filterCategory'),
                    placeholder: this.$t('sw-blog.list.textAllCategories'),
                    options: this.categoryFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
                'author-filter': {
                    property: 'authorId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-blog.list.filterAuthor'),
                    placeholder: this.$t('sw-blog.list.textAllAuthors'),
                    options: this.authorFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return blogFilterFactory(this).create('blog', this.listFilterOptions);
        },

        canEdit(): boolean {
            return this.acl.can('blog.editor');
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
                const name = this.authors.find((author) => author.id === this.presetAuthorId)?.name ?? this.presetAuthorId;

                chips.push({ type: 'author', label: `${this.$t('sw-blog.list.filterAuthor')}: ${name}` });
            }

            if (this.presetCategoryId !== null) {
                const name =
                    this.categories.find((category) => category.id === this.presetCategoryId)?.name ??
                    this.presetCategoryId;

                chips.push({ type: 'category', label: `${this.$t('sw-blog.list.filterCategory')}: ${name}` });
            }

            return chips;
        },
    },

    created() {
        this.applyRouteQuery();

        void this.getList();
        void this.loadCategories();
        void this.loadAuthors();
        void this.loadPendingCommentCount();
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

            const filterService = Shopwell.Service('filterService') as unknown as {
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
            this.pendingFilters = filters;
            this.activeFilterNumber = filters.length;

            void this.getList();
        },

        /**
         * The categories and the authors feed the filters of this list, not the entries - they are
         * read once and only again when the merchant reloads.
         */
        async loadCategories(): Promise<void> {
            const criteria = blogApi(this).categoryListCriteria(1, 100);

            await this.categoryRepository.search(criteria).then((result) => {
                this.categories = [...result];
            });
        },

        async loadAuthors(): Promise<void> {
            const criteria = blogApi(this).authorListCriteria(1, 100);

            await this.authorRepository.search(criteria).then((result) => {
                this.authors = [...result];
            });
        },

        /**
         * The number in the toolbar that leads to the moderation queue: how much is waiting right now,
         * read with one count and not by loading the comments.
         */
        async loadPendingCommentCount(): Promise<void> {
            const criteria = blogApi(this).pendingCommentsCriteria();

            await this.commentRepository.search(criteria).then((result) => {
                this.pendingCommentCount = result.total ?? 0;
            });
        },

        onRefresh(): void {
            void this.getList();
            void this.loadCategories();
            void this.loadAuthors();
            void this.loadPendingCommentCount();
        },

        onOpenCreateModal(): void {
            // creating does not ask for a title first: the entry page is where an entry is written,
            // and a dialog that asks for the headline before the page that owns the headline is one
            // step that teaches nothing
            void this.$router.push({ name: 'sw.blog.create' });
        },

        onInlineEditSave(blog: Blog): void {
            void this.blogRepository.save(blog).then(() => this.getList());
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

        onOpenCategoryList(): void {
            void this.$router.push({ name: 'sw.blog.category.list' });
        },

        onOpenCommentList(): void {
            void this.$router.push({ name: 'sw.blog.comment.list' });
        },
    },
});
