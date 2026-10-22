/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-author-list.html.twig';
import './sw-blog-author-list.scss';
import { blogApi } from '../../service/blog.api.service';

const { Mixin } = Shopwell;

/**
 * An author row as this list reads it: the record plus the entries that carry its byline.
 *
 * `entries` is an association and not a column - counting them here is one request for the whole
 * page instead of one per row.
 */
type AuthorRow = EntitySchema.Entity<'blog_author'> & {
    entries?: Array<{ id: string }> | null;
};

type DeleteTarget = {
    id: EntitySchema.EntityKey<'blog_author'>;
    name: string;
    entryCount: number;
};

/**
 * Every author of the blog.
 *
 * The page exists because an entry is signed with a person, and that person has to be creatable
 * somewhere: without this list the author selector of the entry page offers an empty dropdown and a
 * merchant cannot sign anything. An author is not a customer and not a user - a guest columnist has
 * no login at all.
 *
 * Authors are never deleted while entries carry their byline: the foreign key is `SET_NULL`, so a
 * delete would silently strip the signature off those entries. The delete dialog therefore offers
 * to switch the author off instead, which keeps the byline and only removes them from the selector
 * of new entries.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        authors: EntityCollection<'blog_author'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        deleteTarget: DeleteTarget | null;
        showCreateModal: boolean;
        newAuthorName: string;
        isCreating: boolean;
    } {
        return {
            authors: null,
            isLoading: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            deleteTarget: null,
            showCreateModal: false,
            newAuthorName: '',
            isCreating: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        authorRepository(): RepositoryType<'blog_author'> {
            return this.repositoryFactory.create('blog_author');
        },

        criteria(): CriteriaType {
            return blogApi(this).authorListCriteria(this.page, this.limit, this.term ?? null);
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'mediaId',
                    label: 'sw-blog.author.columnAvatar',
                    width: '80px',
                },
                {
                    property: 'name',
                    label: 'sw-blog.author.columnName',
                    primary: true,
                    inlineEdit: 'string',
                    allowResize: true,
                },
                {
                    property: 'email',
                    label: 'sw-blog.author.columnEmail',
                    allowResize: true,
                },
                {
                    property: 'entries',
                    label: 'sw-blog.author.columnEntryCount',
                    width: '120px',
                },
                {
                    property: 'active',
                    label: 'sw-blog.author.columnActive',
                    width: '120px',
                },
            ];
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

        isCreateDisabled(): boolean {
            return this.newAuthorName.trim() === '';
        },

        /**
         * The delete question, with the number of entries that would lose their byline.
         *
         * The number is the point of the sentence: "3 articles will keep their text but lose the
         * author" is a decision, "this author is in use" is a warning nobody can weigh.
         */
        deleteMessage(): string {
            const entryCount = this.deleteTarget?.entryCount ?? 0;

            if (entryCount > 0) {
                return this.$t('sw-blog.author.textDeleteInUse', {
                    name: this.deleteTarget?.name ?? '',
                    count: entryCount,
                });
            }

            return this.$t('sw-blog.author.textDelete', { name: this.deleteTarget?.name ?? '' });
        },

        isDeleteBlocked(): boolean {
            return (this.deleteTarget?.entryCount ?? 0) > 0;
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            return this.authorRepository
                .search(this.criteria)
                .then((result) => {
                    this.total = result.total ?? 0;
                    this.authors = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): void {
            void this.getList();
        },

        onOpenBlogList(): void {
            void this.$router.push({ name: 'sw.blog.list' });
        },

        entryCountOf(author: AuthorRow): number {
            return author.entries?.length ?? 0;
        },

        /**
         * The first letter of the name, for the rows without a portrait.
         *
         * An empty circle is a hole in the table; a letter is a placeholder that is still readable as
         * "this is a person".
         */
        initialOf(author: AuthorRow): string {
            return (author.name ?? '').trim().charAt(0).toUpperCase();
        },

        onOpenCreateModal(): void {
            this.newAuthorName = '';
            this.showCreateModal = true;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
            this.newAuthorName = '';
        },

        /**
         * An author needs nothing but a name. Email, portrait and biography are filled in on the
         * detail page - asking for all of them before the merchant knows who the author is would turn
         * "add a byline" into a form.
         */
        onCreateAuthor(): void {
            const name = this.newAuthorName.trim();

            if (name === '' || this.isCreating) {
                return;
            }

            this.isCreating = true;

            const author = this.authorRepository.create();

            author.name = name;
            author.active = true;

            void this.authorRepository
                .save(author)
                .then(() => {
                    this.showCreateModal = false;

                    return this.$router.push({
                        name: 'sw.blog.author.detail',
                        params: { id: author.id },
                    });
                })
                .finally(() => {
                    this.isCreating = false;
                });
        },

        onInlineEditSave(author: EntitySchema.Entity<'blog_author'>): void {
            void this.authorRepository.save(author).then(() => this.getList());
        },

        /**
         * Switching an author off keeps every byline: the entries point at the row, and the row keeps
         * its name. Only the selector of new entries stops offering it.
         */
        onChangeActive(author: EntitySchema.Entity<'blog_author'>): void {
            void this.authorRepository.save(author);
        },

        onDelete(author: AuthorRow): void {
            this.deleteTarget = {
                id: author.id,
                name: author.name,
                entryCount: this.entryCountOf(author),
            };
        },

        onCloseDeleteModal(): void {
            this.deleteTarget = null;
        },

        onConfirmDelete(): void {
            if (!this.deleteTarget || this.isDeleteBlocked) {
                return;
            }

            const { id } = this.deleteTarget;

            this.deleteTarget = null;

            void this.authorRepository.delete(id).then(() => this.getList());
        },

        /**
         * Removing an author who is still in use is done by switching them off, not by deleting them.
         */
        onDeactivateInstead(): void {
            if (!this.deleteTarget) {
                return;
            }

            const { id } = this.deleteTarget;

            this.deleteTarget = null;

            void this.authorRepository
                .get(id, Shopwell.Context.api)
                .then((author) => {
                    if (!author) {
                        return null;
                    }

                    author.active = false;

                    return this.authorRepository.save(author);
                })
                .then(() => this.getList());
        },

        /**
         * The entries of one author, opened in the entry list that is already filtered for them.
         */
        onOpenAuthorEntries(author: AuthorRow): void {
            void this.$router.push({
                name: 'sw.blog.list',
                query: { authorId: author.id },
            });
        },
    },
});
