/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-blog-detail-comments.html.twig';
import './sw-blog-detail-comments.scss';
import type { BlogComment } from '../../service/blog.api.service';
import { blogApi } from '../../service/blog.api.service';

/**
 * The comments of one entry, as the moderator sees them.
 *
 * The entity declares `customerId` without an association, on purpose: the blog domain must not grow
 * a reverse association on `customer`. The display name of a customer comment is therefore resolved
 * with one extra request for the ids that actually appear in the thread, and a guest comment keeps
 * the name it was written with.
 *
 * Approving and hiding flip `status`, which is the same flag the storefront filters on - a comment
 * has two states, so there is no state machine and nothing to configure.
 *
 * Deleting a comment also deletes its replies: a reply has no meaning without the comment it answers,
 * and leaving it behind would show up in the storefront as a thread starting in the middle of a
 * sentence. The confirmation names the number of replies, counted from the rows already loaded.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        blogId: {
            type: String,
            required: true,
        },

        canEdit: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        comments: BlogComment[];
        customerNames: Record<string, string>;
        isLoading: boolean;
        deleteTarget: BlogComment | null;
    } {
        return {
            comments: [],
            customerNames: {},
            isLoading: false,
            deleteTarget: null,
        };
    },

    computed: {
        /**
         * The comment repository.
         *
         * The thread is read on its own request instead of through `blog.comments`: the moderation
         * grid is only shown when the merchant scrolls to it, an association would put every approved
         * and unapproved comment of an entry into the payload of the entry itself, and the grid can
         * reload after a moderation decision without re-reading the entry and losing the edit.
         */
        commentRepository(): RepositoryType<'blog_comment'> {
            return this.repositoryFactory.create('blog_comment');
        },

        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'externalUser',
                    label: 'sw-blog.detail.commentsColumnAuthor',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'content',
                    label: 'sw-blog.detail.commentsColumnContent',
                    allowResize: true,
                },
                {
                    property: 'parentId',
                    label: 'sw-blog.detail.commentsColumnDepth',
                    width: '100px',
                },
                {
                    property: 'status',
                    label: 'sw-blog.detail.commentsColumnStatus',
                    width: '140px',
                },
                {
                    property: 'createdAt',
                    label: 'sw-blog.detail.commentsColumnCreatedAt',
                    allowResize: true,
                },
            ];
        },

        replyCount(): number {
            return this.deleteTarget ? blogApi(this).replyCountOf(this.comments, this.deleteTarget.id) : 0;
        },

        deleteMessage(): string {
            return this.$t('sw-blog.detail.commentsTextDelete', { count: this.replyCount });
        },
    },

    created() {
        void this.load();
    },

    methods: {
        async load(): Promise<void> {
            this.isLoading = true;

            try {
                const criteria: CriteriaType = blogApi(this).commentsCriteria(this.blogId);
                const result = await this.commentRepository.search(criteria);

                this.comments = [...result];
                await this.loadCustomerNames();
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * One request for the customers of the thread, not one per comment.
         *
         * The displayed name is the `name` column of the customer, which the server materialises from
         * the first and last name of the billing address - reading `firstName` here would need an
         * association the comment does not have.
         */
        async loadCustomerNames(): Promise<void> {
            const customerIds = [
                ...new Set(
                    this.comments
                        .map((comment) => comment.customerId)
                        .filter((customerId): customerId is EntitySchema.EntityKey<'customer'> => {
                            return typeof customerId === 'string';
                        }),
                ),
            ];

            if (customerIds.length === 0) {
                this.customerNames = {};

                return;
            }

            const criteria = blogApi(this).customerCriteria(customerIds);
            const result = await this.customerRepository.search(criteria);
            const names: Record<string, string> = {};

            [...result].forEach((customer) => {
                names[customer.id] = customer.name !== '' ? customer.name : customer.email;
            });

            this.customerNames = names;
        },

        authorNameOf(comment: BlogComment): string {
            return blogApi(this).authorNameOf(comment, this.customerNames);
        },

        excerptOf(comment: BlogComment): string {
            return blogApi(this).excerptOf(comment);
        },

        depthOf(comment: BlogComment): number {
            return blogApi(this).depthOf(comment, this.comments);
        },

        statusLabelOf(comment: BlogComment): string {
            return comment.status
                ? this.$t('sw-blog.detail.commentsStatusApproved')
                : this.$t('sw-blog.detail.commentsStatusPending');
        },

        onApprove(comment: BlogComment): void {
            void this.setStatus(comment, true);
        },

        onHide(comment: BlogComment): void {
            void this.setStatus(comment, false);
        },

        async setStatus(comment: BlogComment, status: boolean): Promise<void> {
            comment.status = status;

            await this.commentRepository.save(comment);
            await this.load();
        },

        onDelete(comment: BlogComment): void {
            this.deleteTarget = comment;
        },

        onCloseDeleteModal(): void {
            this.deleteTarget = null;
        },

        async onConfirmDelete(): Promise<void> {
            const target = this.deleteTarget;

            if (!target) {
                return;
            }

            this.deleteTarget = null;

            const replies = this.comments.filter((comment) => comment.parentId === target.id);

            for (const reply of replies) {
                await this.commentRepository.delete(reply.id);
            }

            await this.commentRepository.delete(target.id);
            await this.load();
        },
    },
});
