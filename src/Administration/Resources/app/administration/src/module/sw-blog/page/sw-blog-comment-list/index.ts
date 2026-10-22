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

/**
 * A comment as the moderation list reads it: the row plus the entry and the customer behind it.
 *
 * Both are associations and not columns of the comment - the entry carries its translated title,
 * the customer carries its account name, and a guest comment has no customer at all.
 */
type CommentRow = EntitySchema.Entity<'blog_comment'> & {
    blog?: { id: string; translated?: { title?: string } } | null;
    customer?: { id: string; firstName?: string | null; lastName?: string | null } | null;
};

/**
 * Every comment of the shop, as a moderation queue.
 *
 * The comments of one article are edited from the article - this page is for the other sitting:
 * a moderator works through what arrived everywhere, newest first, and approves or rejects without
 * opening a single entry. That is the same split the review module draws between its list and the
 * product it reviews, and the reason the page exists instead of a tab.
 *
 * `status` is a plain bool, so the moderation state has no machine to step through: the switch in
 * the row flips it, and the filter above the grid narrows the queue to what still waits.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        comments: EntityCollection<'blog_comment'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        statusFilter: 'pending' | 'approved' | 'all';
        deleteTarget: CommentRow | null;
    } {
        return {
            comments: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            // the queue opens on what waits, not on everything - the moderator came here to decide
            statusFilter: 'pending',
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

        criteria(): CriteriaType {
            // the filter is a word for the template but a bool for the search: the two states of a
            // comment are a column of the table, and `all` simply removes the filter
            const status = this.statusFilter === 'pending' ? false : this.statusFilter === 'approved' ? true : null;

            return blogApi(this).commentListCriteria(this.page, this.limit, status, this.term ?? null);
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'author',
                    label: 'sw-blog.comment.columnAuthor',
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
                    property: 'status',
                    label: 'sw-blog.comment.columnStatus',
                    width: '120px',
                },
                {
                    property: 'createdAt',
                    label: 'sw-blog.comment.columnCreatedAt',
                    width: '160px',
                },
            ];
        },

        statusOptions(): Array<{ value: string; label: string }> {
            return [
                { value: 'pending', label: this.$t('sw-blog.comment.filterPending') },
                { value: 'approved', label: this.$t('sw-blog.comment.filterApproved') },
                { value: 'all', label: this.$t('sw-blog.comment.filterAll') },
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
        async getList(): Promise<void> {
            this.isLoading = true;

            return this.commentRepository
                .search(this.criteria)
                .then((result) => {
                    this.total = result.total ?? 0;
                    this.comments = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): void {
            void this.getList();
        },

        /**
         * A narrower queue that still starts on page 4 shows an empty list for a reason nobody can
         * see, so a filter change resets the pagination like it does on every other list.
         */
        onChangeStatusFilter(): void {
            this.page = 1;
            void this.getList();
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
            if (comment.externalUser) {
                return comment.externalUser;
            }

            const customer = comment.customer;

            if (customer) {
                return [customer.firstName, customer.lastName].filter(Boolean).join(' ') || comment.id;
            }

            return this.$t('sw-blog.comment.textGuest');
        },

        /**
         * The title of the entry a comment belongs to, falling back to its id - a deleted entry
         * must not blank the row that proves somebody once commented on it.
         */
        blogTitleOf(comment: CommentRow): string {
            return comment.blog?.translated?.title || comment.blogId;
        },

        /**
         * Approving is one flip of the column the storefront filters on. The row keeps its place
         * until the list reloads, so a moderator sees the switch settle before the row moves.
         */
        onChangeStatus(comment: CommentRow): void {
            void this.commentRepository.save(comment).then(() => this.getList());
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
