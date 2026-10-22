/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-category-detail.html.twig';
import './sw-blog-category-detail.scss';
import { blogApi } from '../../service/blog.api.service';

type BlogCategory = EntitySchema.Entity<'blog_category'>;

type CmsPage = EntitySchema.Entity<'cms_page'>;

/**
 * One category of the blog.
 *
 * A category is flat: there is no parent, so the page is a form and not a tree. The three fields are
 * translated per language - a shop that writes in two languages wants a readable name and a readable
 * url in both - which is why the page carries the language switch of the entry page as well.
 *
 * The layout belongs to the category and not to a global setting: two categories may want different
 * densities, and a shop wide switch would force one on the other.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        categoryId: {
            type: String as unknown as PropType<EntitySchema.EntityKey<'blog_category'>>,
            required: true,
        },
    },

    data(): {
        category: BlogCategory | null;
        cmsPage: CmsPage | null;
        isLoading: boolean;
        isSaving: boolean;
        showDeleteModal: boolean;
    } {
        return {
            category: null,
            cmsPage: null,
            isLoading: false,
            isSaving: false,
            showDeleteModal: false,
        };
    },

    computed: {
        categoryRepository(): RepositoryType<'blog_category'> {
            return this.repositoryFactory.create('blog_category');
        },

        cmsPageRepository(): RepositoryType<'cms_page'> {
            return this.repositoryFactory.create('cms_page');
        },

        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        cmsPageOptionsCriteria(): CriteriaType {
            return blogApi(this).categoryCmsPageOptionsCriteria();
        },

        name(): string {
            return this.category?.name ?? '';
        },

        isNameMissing(): boolean {
            return this.name.trim() === '';
        },

        slug(): string {
            return this.category?.slug ?? '';
        },

        /**
         * The delete question with the category name filled in.
         *
         * The interpolation is done here and not in the template: a `$t` call with an object literal
         * inside a template tag breaks the twig compile of this administration, which leaves the
         * dialog blank instead of asking anything.
         */
        deleteMessage(): string {
            return this.$t('sw-blog.list.textDeleteCategory', { name: this.name });
        },

        isSlugMissing(): boolean {
            return this.slug.trim() === '';
        },
    },

    created() {
        void this.loadCategory();
    },

    methods: {
        async loadCategory(): Promise<void> {
            this.isLoading = true;

            try {
                const criteria = blogApi(this).categoryDetailCriteria(this.categoryId);
                const category = await this.categoryRepository.get(this.categoryId, Shopwell.Context.api, criteria);

                this.category = category ?? null;

                this.applyActiveDefault();

                await this.loadCmsPage();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Gives a category that carries no `active` value the default of a new one.
         *
         * `active` is a required field of the definition, so a write without it fails with
         * "Column 'active' cannot be null". The list creates a category with the value already set,
         * but a category can also be opened by a deep link right after it was written by an
         * integration - and then the form has to bring the value, not the caller.
         *
         * A category that is deliberately inactive keeps its `false`; only a missing value is filled.
         */
        applyActiveDefault(): void {
            if (this.category === null) {
                return;
            }

            const active: unknown = this.category.active;

            if (active === null || active === undefined) {
                this.category.active = true;
            }
        },

        async loadCmsPage(): Promise<void> {
            const cmsPageId = this.category?.cmsPageId ?? null;

            if (!cmsPageId) {
                this.cmsPage = null;

                return;
            }

            const criteria = blogApi(this).cmsPageCriteria(cmsPageId);

            try {
                const page = await this.cmsPageRepository.get(cmsPageId, Shopwell.Context.api, criteria);
                this.cmsPage = page ?? null;
            } catch {
                this.cmsPage = null;
            }
        },

        onCmsPageChanged(): void {
            void this.loadCmsPage();
        },

        /**
         * Writes the version column of the layout reference, and only together with its id.
         *
         * A reference into the version aware `cms_page` spans two columns, so both are written in one
         * go. Without a chosen layout both are left untouched - the cleared id is what makes the
         * storefront fall back to its own listing template.
         */
        applyCmsPage(): void {
            if (!this.category || !this.category.cmsPageId) {
                return;
            }

            if (this.cmsPage?.versionId) {
                this.category.cmsPageVersionId = this.cmsPage.versionId;
            }
        },

        /**
         * Saving stays on the page, the same way the entry page keeps it: a category is corrected in
         * several passes, and a save that jumps back to the list makes the merchant find it again
         * after each one.
         */
        async onSave(redirect: boolean = false): Promise<void> {
            if (!this.category || this.isSaving) {
                return;
            }

            if (this.isNameMissing) {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageTitleRequired') });

                return;
            }

            if (this.isSlugMissing) {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSlugRequired') });

                return;
            }

            this.isSaving = true;

            try {
                this.applyCmsPage();

                await this.categoryRepository.save(this.category);

                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageSaveSuccess') });

                if (redirect) {
                    await this.$router.push({ name: 'sw.blog.category.list' });

                    return;
                }

                await this.loadCategory();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
            } finally {
                this.isSaving = false;
            }
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.blog.category.list' });
        },

        onOpenDeleteModal(): void {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal(): void {
            this.showDeleteModal = false;
        },

        onDelete(): void {
            this.showDeleteModal = false;

            void this.categoryRepository
                .delete(this.categoryId)
                .then(() => {
                    this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageDeleteSuccess') });

                    return this.$router.push({ name: 'sw.blog.category.list' });
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                });
        },

        abortOnLanguageChange(): boolean {
            return this.category ? this.categoryRepository.hasChanges(this.category) : false;
        },

        saveOnLanguageChange(): Promise<void> {
            return this.onSave(false);
        },

        onChangeLanguage(languageId: EntitySchema.EntityKey<'language'>): void {
            this.setApiLanguageId(languageId);
            void this.loadCategory();
        },

        /**
         * The api language lives in the context store; the repository reads it on the next request,
         * which is what turns the reload into a translated reload.
         */
        setApiLanguageId(languageId: EntitySchema.EntityKey<'language'>): void {
            Shopwell.Store.get('context').setApiLanguageId(languageId);
        },
    },
});
