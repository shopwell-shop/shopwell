/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-category-list.html.twig';
import './sw-blog-category-list.scss';
import { blogApi } from '../../service/blog.api.service';

const { Mixin } = Shopwell;

type BlogCategory = EntitySchema.Entity<'blog_category'>;

type DeleteTarget = {
    id: EntitySchema.EntityKey<'blog_category'>;
    name: string | null;
    entryCount: number;
};

/**
 * Every category of the blog.
 *
 * A category is how a reader browses the blog, so the list is ordered by name and not by date: a
 * merchant opens it to find the bucket he is looking for, not the one he touched last. The entry
 * count comes from the assignments that were read along - a blog has dozens of categories, and one
 * association is cheaper than an aggregation the server would compute for every request.
 *
 * The categories have no menu entry of their own: the toolbar of the entry list is the door.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        categories: EntityCollection<'blog_category'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        deleteTarget: DeleteTarget | null;
        showCreateModal: boolean;
        newCategoryName: string;
        isCreating: boolean;
    } {
        return {
            categories: null,
            isLoading: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            deleteTarget: null,
            showCreateModal: false,
            newCategoryName: '',
            isCreating: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        categoryRepository(): RepositoryType<'blog_category'> {
            return this.repositoryFactory.create('blog_category');
        },

        criteria(): CriteriaType {
            return blogApi(this).categoryListCriteria(this.page, this.limit, this.term ?? null);
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'name',
                    label: 'sw-blog.list.columnName',
                    primary: true,
                    inlineEdit: 'string',
                    allowResize: true,
                },
                {
                    property: 'slug',
                    label: 'sw-blog.list.columnSlug',
                    inlineEdit: 'string',
                    allowResize: true,
                },
                {
                    property: 'description',
                    label: 'sw-blog.list.columnDescription',
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: 'sw-blog.list.columnActive',
                    inlineEdit: 'boolean',
                    width: '120px',
                },
                {
                    property: 'assignments',
                    label: 'sw-blog.list.columnArticleCount',
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

        deleteName(): string {
            return this.deleteTarget?.name ?? '';
        },

        deleteMessage(): string {
            const entryCount = this.deleteTarget?.entryCount ?? 0;

            if (entryCount > 0) {
                return this.$t('sw-blog.category.textDeleteInUse', {
                    count: entryCount,
                });
            }

            return this.$t('sw-blog.list.textDeleteCategory', { name: this.deleteName });
        },

        isCreateDisabled(): boolean {
            return this.newCategoryName.trim() === '';
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            return this.categoryRepository
                .search(this.criteria)
                .then((result) => {
                    this.total = result.total ?? 0;
                    this.categories = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): void {
            void this.getList();
        },

        onOpenCreateModal(): void {
            this.newCategoryName = '';
            this.showCreateModal = true;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
            this.newCategoryName = '';
        },

        /**
         * A category needs a name and a slug, and the slug is unique per language. The name is asked
         * for here, the slug is generated and corrected on the detail page.
         */
        onCreateCategory(): void {
            const name = this.newCategoryName.trim();

            if (name === '' || this.isCreating) {
                return;
            }

            this.isCreating = true;

            const category = this.categoryRepository.create();

            category.name = name;
            category.slug = this.uniqueSlugFor(name);
            category.active = true;

            void this.categoryRepository
                .save(category)
                .then(() => {
                    this.showCreateModal = false;

                    return this.$router.push({
                        name: 'sw.blog.category.detail',
                        params: { id: category.id },
                    });
                })
                .finally(() => {
                    this.isCreating = false;
                });
        },

        uniqueSlugFor(name: string): string {
            const base = name
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            const suffix = Date.now().toString(36);

            return base === '' ? `${this.$t('sw-blog.list.defaultCategorySlug')}-${suffix}` : `${base}-${suffix}`;
        },

        onInlineEditSave(category: BlogCategory): void {
            void this.categoryRepository.save(category).then(() => this.getList());
        },

        onDelete(category: BlogCategory): void {
            this.deleteTarget = {
                id: category.id,
                name: category.name,
                entryCount: category.assignments?.length ?? 0,
            };
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

            void this.categoryRepository.delete(id).then(() => this.getList());
        },

        onOpenBlogList(): void {
            void this.$router.push({ name: 'sw.blog.list' });
        },

        /**
         * The count in the column is a door: a merchant who reads "5 articles" wants to see them,
         * not to reconstruct the same question as a filter on another page.
         */
        onOpenCategoryEntries(category: BlogCategory): void {
            void this.$router.push({
                name: 'sw.blog.list',
                query: { categoryId: category.id },
            });
        },
    },
});
