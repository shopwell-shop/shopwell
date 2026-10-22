/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-author-detail.html.twig';
import './sw-blog-author-detail.scss';
import { blogApi } from '../../service/blog.api.service';

type BlogAuthor = EntitySchema.Entity<'blog_author'>;

/**
 * One author.
 *
 * The page has two jobs. It is the place a byline is maintained - name, biography, portrait, the
 * administration-only email, the switch that keeps the author out of the selector of new entries -
 * and it is the answer to "what did this person write", which is the only reason an editor opens an
 * author they did not just create.
 *
 * The entries are read through their own repository and not through `author.entries`: the author
 * carries the association, but a typed entity would have to be widened for it, and the list needs
 * only three columns of the entry.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        authorId: {
            type: String as unknown as PropType<EntitySchema.EntityKey<'blog_author'>>,
            required: true,
        },
    },

    data(): {
        author: BlogAuthor | null;
        entries: EntityCollection<'blog'> | null;
        isLoading: boolean;
        isSaving: boolean;
        mediaUploadTag: string;
        showDeleteModal: boolean;
    } {
        return {
            author: null,
            entries: null,
            isLoading: false,
            isSaving: false,
            mediaUploadTag: 'sw-blog-author-detail-avatar',
            showDeleteModal: false,
        };
    },

    computed: {
        authorRepository(): RepositoryType<'blog_author'> {
            return this.repositoryFactory.create('blog_author');
        },

        blogRepository(): RepositoryType<'blog'> {
            return this.repositoryFactory.create('blog');
        },

        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        name(): string {
            return this.author?.name ?? '';
        },

        isNameMissing(): boolean {
            return this.name.trim() === '';
        },

        entryCount(): number {
            return this.entries?.length ?? 0;
        },

        /**
         * An author who still signs entries is not deleted: the foreign key is `SET_NULL`, so the
         * delete would strip the byline off every one of them without asking again. Switching the
         * author off removes them from the selector of new entries and leaves the old bylines alone.
         */
        isDeleteBlocked(): boolean {
            return this.entryCount > 0;
        },

        deleteMessage(): string {
            if (this.isDeleteBlocked) {
                return this.$t('sw-blog.author.textDeleteInUse', {
                    name: this.name,
                    count: this.entryCount,
                });
            }

            return this.$t('sw-blog.author.textDelete', { name: this.name });
        },

        entriesColumns(): Array<Record<string, unknown>> {
            return [
                { property: 'title', label: 'sw-blog.author.columnEntryTitle', primary: true },
                { property: 'publishedAt', label: 'sw-blog.author.columnEntryPublishedAt', width: '180px' },
            ];
        },
    },

    created() {
        void this.loadAuthor();
    },

    methods: {
        async loadAuthor(): Promise<void> {
            this.isLoading = true;

            try {
                const criteria = blogApi(this).authorDetailCriteria(this.authorId);
                const author = await this.authorRepository.get(this.authorId, Shopwell.Context.api, criteria);

                this.author = author ?? null;

                await this.loadEntries();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isLoading = false;
            }
        },

        async loadEntries(): Promise<void> {
            const criteria = blogApi(this).entriesOfAuthorCriteria(this.authorId);

            await this.blogRepository.search(criteria).then((result) => {
                this.entries = result;
            });
        },

        onAvatarSelected(media: Array<Pick<EntitySchema.Entity<'media'>, 'id'>>): void {
            this.setAvatar(media[0]?.id ?? null);
        },

        onAvatarRemoved(): void {
            this.setAvatar(null);
        },

        /**
         * `mediaId` is a nullable column: removing the portrait has to reach the api as an explicit
         * null, otherwise the author keeps the picture that was just deleted.
         */
        setAvatar(mediaId: EntitySchema.EntityKey<'media'> | null): void {
            if (!this.author) {
                return;
            }

            this.author.mediaId = mediaId as EntitySchema.EntityKey<'media'>;
        },

        async onSave(redirect: boolean = false): Promise<void> {
            if (!this.author || this.isSaving) {
                return;
            }

            if (this.isNameMissing) {
                this.createNotificationError({ message: this.$t('sw-blog.author.messageNameRequired') });

                return;
            }

            this.isSaving = true;

            try {
                await this.authorRepository.save(this.author);

                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageSaveSuccess') });

                if (redirect) {
                    await this.$router.push({ name: 'sw.blog.author.list' });

                    return;
                }

                await this.loadAuthor();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
            } finally {
                this.isSaving = false;
            }
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.blog.author.list' });
        },

        onOpenDeleteModal(): void {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal(): void {
            this.showDeleteModal = false;
        },

        onDelete(): void {
            if (this.isDeleteBlocked) {
                return;
            }

            this.showDeleteModal = false;

            void this.authorRepository
                .delete(this.authorId)
                .then(() => {
                    this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageDeleteSuccess') });

                    return this.$router.push({ name: 'sw.blog.author.list' });
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                });
        },

        onDeactivateInstead(): void {
            if (!this.author) {
                return;
            }

            this.author.active = false;
            this.showDeleteModal = false;

            void this.onSave();
        },

        abortOnLanguageChange(): boolean {
            return this.author ? this.authorRepository.hasChanges(this.author) : false;
        },

        saveOnLanguageChange(): Promise<void> {
            return this.onSave(false);
        },

        onChangeLanguage(languageId: EntitySchema.EntityKey<'language'>): void {
            this.setApiLanguageId(languageId);
            void this.loadAuthor();
        },

        setApiLanguageId(languageId: EntitySchema.EntityKey<'language'>): void {
            Shopwell.Store.get('context').setApiLanguageId(languageId);
        },
    },
});
