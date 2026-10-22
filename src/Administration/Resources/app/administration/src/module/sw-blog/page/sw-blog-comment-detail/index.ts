/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-comment-detail.html.twig';
import './sw-blog-comment-detail.scss';
import { blogApi } from '../../service/blog.api.service';

const { Criteria } = Shopwell.Data;

type BlogComment = EntitySchema.Entity<'blog_comment'> & {
    blog?: { id: string; translated?: { title?: string } } | null;
};

/**
 * One comment, laid out like a product review.
 *
 * The page answers the two questions a moderator has about a single comment and answers nothing
 * else: what was written (the text with the entry it was written under), and what state it is in
 * (the switch in the properties card). The text itself is not editable - it is what a reader said,
 * and rewriting it in the administration would leave the author with words they never wrote.
 *
 * The state is a plain bool, so the whole moderation vocabulary of this page is one switch. The list
 * offers the same decision as a row action for the comments whose state is obvious from the excerpt;
 * both write the same column, so the two screens can not disagree.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        commentId: {
            type: String as unknown as PropType<EntitySchema.EntityKey<'blog_comment'>>,
            required: true,
        },
    },

    data(): {
        comment: BlogComment | null;
        customerName: string;
        customerEmail: string;
        isLoading: boolean;
        isSaving: boolean;
    } {
        return {
            comment: null,
            customerName: '',
            customerEmail: '',
            isLoading: false,
            isSaving: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.headerTitle),
        };
    },

    computed: {
        commentRepository(): RepositoryType<'blog_comment'> {
            return this.repositoryFactory.create('blog_comment');
        },

        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },

        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },

        /**
         * What the header says. A comment without a headline is the normal case, so the first words
         * of the text stand in - an empty header reads like a page that failed to load.
         */
        headerTitle(): string {
            if (!this.comment) {
                return this.$t('sw-blog.comment.detailTitle');
            }

            return this.comment.title || blogApi(this).excerptOf(this.comment, 60) || this.$t('sw-blog.comment.detailTitle');
        },

        blogTitle(): string {
            return this.comment?.blog?.translated?.title || this.comment?.blogId || '';
        },

        /**
         * Who wrote it, in the order the entity stores it: a guest typed a name, a customer owns one
         * in their account. Both fields are read-only here - the author is not something the shop
         * edits on a comment.
         */
        authorName(): string {
            return this.comment?.externalUser || this.customerName || this.$t('sw-blog.comment.textGuest');
        },

        authorEmail(): string {
            return this.comment?.externalEmail || this.customerEmail;
        },

        /**
         * The languages in one alphabetical list, so the select of the properties card opens on a
         * list a merchant can scan instead of on the ids of the shop.
         */
        languageCriteria(): CriteriaType {
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC'));
        },

        /**
         * The state as a word, for the tooltip of the switch and the subtitle of the card.
         */
        statusLabel(): string {
            return this.comment?.status ? this.$t('sw-blog.comment.filterApproved') : this.$t('sw-blog.comment.filterPending');
        },
    },

    watch: {
        commentId() {
            void this.loadComment();
        },
    },

    created() {
        void this.loadComment();
    },

    methods: {
        async loadComment(): Promise<void> {
            this.isLoading = true;

            try {
                const criteria = blogApi(this).commentDetailCriteria(this.commentId);

                this.comment = await this.commentRepository.get(this.commentId, Shopwell.Context.api, criteria);

                await this.loadCustomer();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.comment.messageLoadError') });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The account behind `customerId`, read separately: the comment has no association into the
         * customer domain, so the name and the address of the author are a second request.
         */
        async loadCustomer(): Promise<void> {
            const customerId = this.comment?.customerId;

            if (!customerId) {
                this.customerName = '';
                this.customerEmail = '';

                return;
            }

            const criteria = blogApi(this).customerCriteria([customerId]);
            const customers = await this.customerRepository.search(criteria);
            const customer = [...customers][0];

            if (!customer) {
                this.customerName = '';
                this.customerEmail = '';

                return;
            }

            this.customerName = [customer.firstName, customer.lastName].filter(Boolean).join(' ') || customer.email;
            this.customerEmail = customer.email;
        },

        /**
         * One column, written and read back: the page reloads so the badge, the switch and the list
         * the moderator returns to all show what the api now holds.
         */
        async onSave(): Promise<void> {
            if (!this.comment || this.isSaving) {
                return;
            }

            this.isSaving = true;

            try {
                await this.commentRepository.save(this.comment);

                this.createNotificationSuccess({ message: this.$t('sw-blog.comment.messageSaveSuccess') });

                await this.loadComment();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.comment.messageSaveError') });
            } finally {
                this.isSaving = false;
            }
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.blog.comment.list' });
        },

        onOpenBlog(): void {
            if (!this.comment) {
                return;
            }

            void this.$router.push({ name: 'sw.blog.detail', params: { id: this.comment.blogId } });
        },
    },
});
