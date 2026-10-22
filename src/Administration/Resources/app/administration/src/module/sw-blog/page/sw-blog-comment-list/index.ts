/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-comment-list.html.twig';
import './sw-blog-comment-list.scss';
import { blogApi } from '../../service/blog.api.service';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

/**
 * The filters the queue offers, in the order a moderator reaches for them: the state first, because
 * the queue exists to decide it, then the entry, because the complaints about one article arrive
 * together.
 */
const DEFAULT_FILTERS = ['status-filter', 'blog-filter'];

/**
 * A comment as the moderation list reads it: the row plus the entry it belongs to.
 *
 * The entry is an association; the customer is not. `blog_comment` keeps `customerId` as a plain
 * foreign key, so the names arrive through a second read and are looked up in `customerNames`.
 */
type CommentRow = EntitySchema.Entity<'blog_comment'> & {
    blog?: { id: string; translated?: { title?: string } } | null;
};

/**
 * Every comment of the shop, laid out like the product reviews.
 *
 * The layout is the one a moderator already knows from `sw-review-list`: the search bar above, the
 * total beside the headline, the state as a badge in the row, the row links into a detail page, and
 * the filters live in the sidebar - a paragraph of a comment does not fit a toolbar, so the narrow
 * decisions are made in the panel and the long ones on the page of the comment.
 *
 * Two things stay from the queue this page was before: the product reviews have no bulk action, but
 * approving twenty comments one page at a time is a chore the selection solves in one click, and
 * `status` is a plain bool, so approving is a write of one column and needs no state machine.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        comments: EntityCollection<'blog_comment'> | null;
        customerNames: Record<string, string>;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        storeKey: string;
        defaultFilters: string[];
        activeFilterNumber: number;
        deleteTarget: CommentRow | null;
    } {
        return {
            comments: null,
            customerNames: {},
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            storeKey: 'grid.filter.blog_comment',
            defaultFilters: DEFAULT_FILTERS,
            activeFilterNumber: 0,
            deleteTarget: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        commentRepository(): RepositoryType<'blog_comment'> {
            return this.repositoryFactory.create('blog_comment');
        },

        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },

        /**
         * The sidebar filters.
         *
         * `status` is a boolean column, so the panel renders it as two named options instead of a
         * text field - "已通过" and "待审核" are the words the row badge uses.
         *
         * `blog` is the association of the row; filing a filter on `blogId` would ask the merchant
         * for a uuid.
         */
        listFilterOptions(): Record<string, Record<string, unknown>> {
            return {
                'status-filter': {
                    property: 'status',
                    type: 'boolean-filter',
                    label: this.$t('sw-blog.comment.filterStatus'),
                    optionTrue: this.$t('sw-blog.comment.filterApproved'),
                    optionFalse: this.$t('sw-blog.comment.filterPending'),
                },
                'blog-filter': {
                    property: 'blog',
                    label: this.$t('sw-blog.comment.filterBlog'),
                    placeholder: this.$t('sw-blog.comment.filterBlogPlaceholder'),
                    criteria: this.blogCriteria,
                    labelProperty: 'title',
                },
            };
        },

        listFilters(): Array<Record<string, unknown>> {
            return this.filterFactory.create('blog_comment', this.listFilterOptions) as Array<Record<string, unknown>>;
        },

        blogCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('title'));

            return criteria;
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'title',
                    label: 'sw-blog.comment.columnTitle',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'content',
                    label: 'sw-blog.comment.columnContent',
                    allowResize: true,
                },
                {
                    property: 'blog',
                    label: 'sw-blog.comment.columnBlog',
                    allowResize: true,
                },
                {
                    property: 'author',
                    label: 'sw-blog.comment.columnAuthor',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-blog.comment.columnCreatedAt',
                    width: '160px',
                },
                {
                    property: 'status',
                    label: 'sw-blog.comment.columnStatus',
                    width: '120px',
                },
            ];
        },

        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        deleteMessage(): string {
            return this.$t('sw-blog.comment.textDelete', { author: this.deleteTarget ? this.authorOf(this.deleteTarget) : '' });
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        /**
         * The criteria is built per call and not cached.
         *
         * The listing mixin asks for the list as well, and a cached criteria handed to
         * `mergeWithStoredFilters` twice collects every active filter twice - the search then answers
         * "no results" for a filter that is set once.
         */
        buildCriteria(): CriteriaType {
            const criteria = blogApi(this).commentListCriteria(this.page, this.limit, null, this.term ?? null);

            this.sortBy.split(',').forEach((sorting) => {
                criteria.addSorting(Criteria.sort(sorting, this.sortDirection, this.naturalSorting));
            });

            return criteria;
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as unknown as {
                mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
            };

            // the service is async - without the await the criteria is a pending promise and reading
            // its filters throws, which leaves the grid permanently empty
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.buildCriteria());

            this.activeFilterNumber = criteria.filters.length;

            return this.commentRepository
                .search(criteria)
                .then(async (result) => {
                    this.total = result.total ?? 0;
                    this.comments = result;

                    await this.loadCustomerNames();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * The names behind the `customerId` of the rows on this page.
         *
         * One request for the whole page, not one per row: the comment stores the id only and the
         * list shows a name, and a page holds at most `limit` of them.
         */
        async loadCustomerNames(): Promise<void> {
            const customerIds = [...(this.comments ?? [])]
                .map((comment) => comment.customerId)
                .filter((customerId): customerId is EntitySchema.EntityKey<'customer'> => {
                    return typeof customerId === 'string';
                });

            const uniqueIds = [...new Set(customerIds)];

            if (uniqueIds.length === 0) {
                this.customerNames = {};

                return;
            }

            const criteria = blogApi(this).customerCriteria(uniqueIds);
            const customers = await this.customerRepository.search(criteria);

            this.customerNames = Object.fromEntries(
                [...customers].map((customer) => {
                    return [customer.id, [customer.firstName, customer.lastName].filter(Boolean).join(' ') || customer.email];
                }),
            );
        },

        /**
         * A narrower list that still shows page 4 shows an empty page for a reason nobody can see,
         * so a filter change resets the pagination like it does on every other list.
         */
        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.activeFilterNumber = filters.length;

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
        },

        /**
         * The headline of a comment, or its first words when it has none - a comment without a title
         * is the normal case, and a blank first column makes the list look broken.
         */
        titleOf(comment: CommentRow): string {
            return comment.title || blogApi(this).excerptOf(comment, 60);
        },

        /**
         * The body of the comment, cut to what a grid row shows; the tooltip carries the whole text.
         */
        excerptOf(comment: CommentRow): string {
            return blogApi(this).excerptOf(comment);
        },

        /**
         * The name of whoever wrote the comment: the guest typed one, the customer owns one, and a
         * comment that lost both is still a row of the queue.
         */
        authorOf(comment: CommentRow): string {
            return blogApi(this).authorNameOf(comment, this.customerNames) || this.$t('sw-blog.comment.textGuest');
        },

        /**
         * The title of the entry a comment belongs to, falling back to its id - a deleted entry
         * must not blank the row that proves somebody once commented on it.
         */
        blogTitleOf(comment: CommentRow): string {
            return comment.blog?.translated?.title || comment.blogId;
        },

        /**
         * Approving and hiding are the same write: the moderation state is one boolean column, and
         * which direction the row moves is decided by the action the moderator picked.
         */
        setStatus(rows: CommentRow[], status: boolean): void {
            const writes = rows.map((row) => {
                row.status = status;

                return this.commentRepository.save(row);
            });

            void Promise.all(writes).then(() => this.getList());
        },

        onApprove(comment: CommentRow): void {
            this.setStatus([comment], true);
        },

        onHide(comment: CommentRow): void {
            this.setStatus([comment], false);
        },

        /**
         * The rows the merchant ticked.
         *
         * `sw-entity-listing` owns the selection, so it is read from the listing ref instead of being
         * mirrored here - a second copy of the selection is a second source of truth for what the
         * bulk action touches.
         */
        selectedRows(): CommentRow[] {
            const listing = this.$refs.listing as { selection?: Record<string, CommentRow> } | undefined;

            return Object.values(listing?.selection ?? {});
        },

        onBulkApprove(): void {
            this.setStatus(this.selectedRows(), true);
        },

        onBulkHide(): void {
            this.setStatus(this.selectedRows(), false);
        },

        onDelete(comment: CommentRow): void {
            this.deleteTarget = comment;
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

            void this.commentRepository.delete(id).then(() => this.getList());
        },

        onOpenBlogList(): void {
            void this.$router.push({ name: 'sw.blog.list' });
        },
    },
});
