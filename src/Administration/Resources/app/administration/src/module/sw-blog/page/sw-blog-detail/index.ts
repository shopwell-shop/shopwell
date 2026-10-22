/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-detail.html.twig';
import './sw-blog-detail.scss';
import type { BlogCategoryAssignment } from '../../service/blog.api.service';
import { blogApi } from '../../service/blog.api.service';
import { registeredBlogTypes } from '../../service/blog-type.registry';

type Blog = EntitySchema.Entity<'blog'>;

type BlogVideo = EntitySchema.Entity<'blog_video'>;

type BlogCategory = EntitySchema.Entity<'blog_category'>;

type CmsPage = EntitySchema.Entity<'cms_page'>;

/**
 * One entry, as a canvas with a settings rail next to it.
 *
 * Three things are worth knowing when reading this page:
 *
 * - There is no `active` switch. Whether the entry is live is derived from `publishedAt` by the same
 *   `blogStatusOf` the list uses, and the rail only shows the result. An entry dated in the future
 *   is a scheduled entry - it exists, the merchant sees it, the storefront hides it.
 * - The left column is not a set of tabs. An entry is written top to bottom - headline, body, seo,
 *   layout - and a tab would hide the body behind a click while the merchant is writing the
 *   headline. Everything that *decides* something about the entry sits in the rail instead, the way
 *   Ghost and WordPress put their publication settings next to the canvas.
 * - Saving stays on the page. An entry is written in several passes, and being dropped back into the
 *   list after each one makes the merchant find the entry again every time.
 *
 * The content card is a host, not an editor: which component edits `blog.type` is decided by the type
 * registry, so a type a plugin brought is edited here as well.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'blogApiService'],

    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        /**
         * The entry to edit, or `null` for a new one.
         *
         * A new entry is created on this page and not in a dialog on the list: the page is where a
         * headline is written, and a dialog that asks for it first is a step that teaches nothing.
         * Nothing is written to the api until the merchant saves - an abandoned draft leaves no row.
         */
        blogId: {
            type: String as unknown as PropType<EntitySchema.EntityKey<'blog'> | null>,
            required: false,
            default: null,
        },
    },

    data(): {
        blog: Blog | null;
        video: BlogVideo | null;
        cmsPage: CmsPage | null;
        assignments: BlogCategoryAssignment[];
        selectedCategoryIds: Array<EntitySchema.EntityKey<'blog_category'>>;
        categories: BlogCategory[];
        pendingCommentCount: number;
        isLoading: boolean;
        isSaving: boolean;
        mediaUploadTag: string;
        showDeleteModal: boolean;
        showCategoryModal: boolean;
        newCategoryName: string;
        isCreatingCategory: boolean;
        showAuthorModal: boolean;
        newAuthorName: string;
        isCreatingAuthor: boolean;
    } {
        return {
            blog: null,
            video: null,
            cmsPage: null,
            assignments: [],
            selectedCategoryIds: [],
            categories: [],
            pendingCommentCount: 0,
            isLoading: false,
            isSaving: false,
            mediaUploadTag: 'sw-blog-detail-cover',
            showDeleteModal: false,
            showCategoryModal: false,
            newCategoryName: '',
            isCreatingCategory: false,
            showAuthorModal: false,
            newAuthorName: '',
            isCreatingAuthor: false,
        };
    },

    computed: {
        blogRepository(): RepositoryType<'blog'> {
            return this.repositoryFactory.create('blog');
        },

        /**
         * The id the page works on: the prop, or the id the new entry got from its first save.
         */
        effectiveId(): EntitySchema.EntityKey<'blog'> | null {
            return this.blog?.id ?? this.blogId ?? null;
        },

        isNew(): boolean {
            return this.blogId === null;
        },

        videoRepository(): RepositoryType<'blog_video'> {
            return this.repositoryFactory.create('blog_video');
        },

        cmsPageRepository(): RepositoryType<'cms_page'> {
            return this.repositoryFactory.create('cms_page');
        },

        assignmentRepository(): RepositoryType<'blog_category_assignment'> {
            return this.repositoryFactory.create('blog_category_assignment');
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

        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },

        canDelete(): boolean {
            return this.acl.can('blog.deleter');
        },

        typeOptions(): Array<{ value: string; label: string }> {
            return registeredBlogTypes().map((type) => {
                const key = blogApi(this).typeLabelKey(type);

                return { value: type, label: this.$te(key) ? this.$t(key) : type };
            });
        },

        cmsPageOptionsCriteria(): CriteriaType {
            return blogApi(this).cmsPageOptionsCriteria();
        },

        title(): string {
            return this.blog?.title ?? '';
        },

        /**
         * What the header says while the entry has no headline yet: an empty header on a new entry
         * reads like a page that failed to load.
         */
        headerTitle(): string {
            return this.title.trim() === '' ? this.$t('sw-blog.list.defaultTitle') : this.title;
        },

        isTitleMissing(): boolean {
            return this.title.trim() === '';
        },

        slug(): string {
            return this.blog?.slug ?? '';
        },

        isSlugMissing(): boolean {
            return this.slug.trim() === '';
        },

        /**
         * The delete question with the entry title filled in.
         *
         * The interpolation is done here and not in the template: a `$t` call with an object literal
         * inside a template tag breaks the twig compile of this administration, which leaves the
         * dialog blank instead of asking anything.
         */
        deleteMessage(): string {
            return this.$t('sw-blog.list.textDeleteBlog', { name: this.title });
        },

        /**
         * The categories as a checkbox list, sorted by name: filing an entry is picking from what
         * exists, and a picker that only shows "3 selected" hides the two the merchant wanted.
         */
        sortedCategories(): BlogCategory[] {
            return [...this.categories].sort((left, right) => {
                return (left.name ?? '').localeCompare(right.name ?? '');
            });
        },

        isCreateCategoryDisabled(): boolean {
            return this.newCategoryName.trim() === '';
        },

        isCreateAuthorDisabled(): boolean {
            return this.newAuthorName.trim() === '';
        },
    },

    created() {
        void this.loadBlog();
        void this.loadCategories();
        void this.loadPendingCommentCount();
    },

    methods: {
        /**
         * A new entry: nothing is written until the merchant saves, so an abandoned draft leaves no
         * row behind. The type is preset because the editor of the body is chosen by it.
         */
        createBlog(): Blog {
            const blog = this.blogRepository.create();

            blog.type = 'post';

            return blog;
        },

        async loadBlog(): Promise<void> {
            this.isLoading = true;

            try {
                const id = this.effectiveId;

                if (!id) {
                    this.blog = this.createBlog();
                    this.ensureVideo();

                    return;
                }

                const criteria = blogApi(this).detailCriteria(id);
                const blog = await this.blogRepository.get(id, Shopwell.Context.api, criteria);

                this.blog = blog ?? null;

                await this.loadAssignments();
                this.ensureVideo();
                await this.loadCmsPage();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The categories are the assignment rows, not the categories themselves: filing an entry is
         * writing a row, and the row is what the page adds and removes.
         */
        async loadAssignments(): Promise<void> {
            const id = this.effectiveId;

            if (!id) {
                this.assignments = [];
                this.selectedCategoryIds = [];

                return;
            }

            const criteria: CriteriaType = blogApi(this).assignmentsOfBlogCriteria(id);
            const result = await this.assignmentRepository.search(criteria);

            this.assignments = [...result];
            this.selectedCategoryIds = this.assignments
                .map((assignment) => assignment.blogCategoryId)
                .filter((categoryId): categoryId is EntitySchema.EntityKey<'blog_category'> => {
                    return typeof categoryId === 'string';
                });
        },

        /**
         * The categories the rail offers as checkboxes: all of them, not only the assigned ones.
         */
        async loadCategories(): Promise<void> {
            const criteria = blogApi(this).categoryListCriteria(1, 100);

            await this.categoryRepository.search(criteria).then((result) => {
                this.categories = [...result];
            });
        },

        /**
         * How many comments of this entry still wait, for the door to the queue.
         */
        async loadPendingCommentCount(): Promise<void> {
            const id = this.effectiveId;

            if (!id) {
                this.pendingCommentCount = 0;

                return;
            }

            const criteria = blogApi(this).pendingCommentsOfBlogCriteria(id);

            await this.commentRepository.search(criteria).then((result) => {
                this.pendingCommentCount = result.total ?? 0;
            });
        },

        isCategorySelected(categoryId: EntitySchema.EntityKey<'blog_category'>): boolean {
            return this.selectedCategoryIds.includes(categoryId);
        },

        /**
         * Filing is a set, not a list: ticking a box adds the id, unticking removes it, and the
         * assignment rows are diffed against this set on save.
         */
        onToggleCategory(categoryId: EntitySchema.EntityKey<'blog_category'>): void {
            this.selectedCategoryIds = this.selectedCategoryIds.includes(categoryId)
                ? this.selectedCategoryIds.filter((selectedId) => selectedId !== categoryId)
                : [...this.selectedCategoryIds, categoryId];
        },

        onOpenCategoryModal(): void {
            this.newCategoryName = '';
            this.showCategoryModal = true;
        },

        onCloseCategoryModal(): void {
            this.showCategoryModal = false;
            this.newCategoryName = '';
        },

        /**
         * A category created while writing is created for the shop, not for this entry: it is saved,
         * added to the checkbox list and ticked here. Leaving the page to create one would lose the
         * paragraph that was just typed.
         */
        onCreateCategory(): void {
            const name = this.newCategoryName.trim();

            if (name === '' || this.isCreatingCategory) {
                return;
            }

            this.isCreatingCategory = true;

            const category = this.categoryRepository.create();

            category.name = name;
            category.slug = blogApi(this).slugFor(name);
            category.active = true;

            void this.categoryRepository
                .save(category)
                .then(async () => {
                    this.showCategoryModal = false;

                    await this.loadCategories();

                    if (!this.selectedCategoryIds.includes(category.id)) {
                        this.selectedCategoryIds = [...this.selectedCategoryIds, category.id];
                    }
                })
                .finally(() => {
                    this.isCreatingCategory = false;
                });
        },

        onOpenAuthorModal(): void {
            this.newAuthorName = '';
            this.showAuthorModal = true;
        },

        onCloseAuthorModal(): void {
            this.showAuthorModal = false;
            this.newAuthorName = '';
        },

        /**
         * The byline is written where the entry is written: the new author is saved, offered in the
         * list and selected right away.
         */
        onCreateAuthor(): void {
            const name = this.newAuthorName.trim();

            if (name === '' || this.isCreatingAuthor) {
                return;
            }

            this.isCreatingAuthor = true;

            const author = this.authorRepository.create();

            author.name = name;
            author.active = true;

            void this.authorRepository
                .save(author)
                .then(() => {
                    this.showAuthorModal = false;

                    if (this.blog) {
                        this.blog.authorId = author.id;
                    }
                })
                .finally(() => {
                    this.isCreatingAuthor = false;
                });
        },

        /**
         * Publishing is an explicit action, not a side effect of saving: the button writes the moment
         * the merchant decides on, and saves so the badge and the storefront agree immediately.
         */
        onPublishNow(): void {
            if (!this.blog) {
                return;
            }

            this.blog.publishedAt = new Date().toISOString();

            void this.onSave();
        },

        /**
         * Clearing the date means "live" in this entity - an entry without a publication date is
         * published, which is the opposite of what "cancelled" usually means. The button therefore
         * says what it does.
         */
        onClearSchedule(): void {
            if (!this.blog) {
                return;
            }

            this.blog.publishedAt = null;

            void this.onSave();
        },

        onOpenCommentList(): void {
            void this.$router.push({ name: 'sw.blog.comment.list' });
        },

        /**
         * A video page edits the video row of the entry. The row may not exist yet - an entry that
         * was turned into a video page has none - so it is created here, unsaved, and written on the
         * next save of the page.
         */
        ensureVideo(): void {
            if (!this.blog || this.blog.type !== 'video') {
                this.video = null;

                return;
            }

            const videos = [...(this.blog.videos ?? [])];

            this.video = videos[0] ?? this.createVideo();
        },

        /**
         * A fresh video row: the url the editor is about to type, no poster and no duration yet -
         * both stay unset so the storefront shows the player instead of a broken image.
         */
        createVideo(): BlogVideo {
            const video = this.videoRepository.create();

            video.blogId = this.effectiveId as EntitySchema.EntityKey<'blog'>;
            video.videoUrl = '';
            video.provider = 'youtube';

            return video;
        },

        /**
         * The layout is read through its own repository: the entry only carries `cmsPageId`, because
         * an association into the cms domain would have to grow a reverse one-to-many on `cms_page`.
         */
        async loadCmsPage(): Promise<void> {
            const cmsPageId = this.blog?.cmsPageId ?? null;

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
         * A reference into the version aware `cms_page` spans two columns: `cmsPageId` names the
         * page, `cmsPageVersionId` the revision it was picked from. Writing the id alone leaves a
         * dangling reference, so both are written together in one go.
         *
         * Without a chosen layout both columns are left untouched: the selection cleared the id, and
         * the version beside it is never read while the id is empty, so the entry falls back to the
         * template the storefront ships with instead of pointing at nothing.
         */
        applyCmsPage(): void {
            if (!this.blog || !this.blog.cmsPageId) {
                return;
            }

            if (this.cmsPage?.versionId) {
                this.blog.cmsPageVersionId = this.cmsPage.versionId;
            }
        },

        onCoverSelected(media: Array<Pick<EntitySchema.Entity<'media'>, 'id'>>): void {
            this.setCover(media[0]?.id ?? null);
        },

        onCoverRemoved(): void {
            this.setCover(null);
        },

        /**
         * Writes the cover reference.
         *
         * `coverMediaId` is a nullable column that the generator types as non-nullable. Removing a
         * cover has to reach the api as an explicit null rather than as an omitted key - otherwise the
         * entry keeps the image the merchant just deleted - so the one nullable assignment of this
         * page is made here instead of at both call sites.
         */
        setCover(mediaId: EntitySchema.EntityKey<'media'> | null): void {
            if (!this.blog) {
                return;
            }

            this.blog.coverMediaId = mediaId as EntitySchema.EntityKey<'media'>;
        },

        /**
         * Writes the entry, the video row and the category rows.
         *
         * The assignments are diffed instead of replaced: removing a row and adding it again would
         * change the `createdAt` of a filed entry, and the filing date is editorial information.
         *
         * Saving stays on the page. An entry is written in several passes - a paragraph now, a
         * category later - and dropping the merchant back into the list after every save would make
         * them find the entry again for each pass. Only an explicit action leaves the page: cancel
         * goes back to the list, so does a delete.
         *
         * @param redirect
         */
        async onSave(redirect: boolean = false): Promise<void> {
            if (!this.blog || this.isSaving) {
                return;
            }

            if (this.isTitleMissing) {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageTitleRequired') });

                return;
            }

            if (this.isSlugMissing) {
                // a new entry gets its url name from its headline: asking for both before the entry
                // even exists is what the create dialog used to do, and it is why it was removed
                if (!this.isNew) {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSlugRequired') });

                    return;
                }

                this.blog.slug = blogApi(this).slugFor(this.title, this.$t('sw-blog.list.defaultSlug'));
            }

            this.isSaving = true;

            try {
                this.applyCmsPage();

                if (this.video) {
                    await this.videoRepository.save(this.video);
                }

                await this.blogRepository.save(this.blog);
                await this.syncCategories();

                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageSaveSuccess') });

                if (redirect) {
                    await this.$router.push({ name: 'sw.blog.list' });

                    return;
                }

                // a new entry now has an id, and the address bar has to say so: a reload of
                // `/create` would open an empty page over content that exists
                if (this.isNew) {
                    await this.$router.replace({ name: 'sw.blog.detail', params: { id: this.blog.id } });
                }

                // reload, so the saved state - and the status badge derived from it - is what the
                // page shows, not the values that were typed
                await this.loadBlog();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
            } finally {
                this.isSaving = false;
            }
        },

        async syncCategories(): Promise<void> {
            const blogId = this.effectiveId;

            if (!blogId) {
                return;
            }

            const idsToRemove = blogApi(this).assignmentIdsToRemove(this.assignments, this.selectedCategoryIds);
            const idsToAssign = blogApi(this).categoryIdsToAssign(this.assignments, this.selectedCategoryIds);

            for (const assignmentId of idsToRemove) {
                await this.assignmentRepository.delete(assignmentId);
            }

            for (const categoryId of idsToAssign) {
                const assignment = this.assignmentRepository.create();

                assignment.blogId = blogId;
                assignment.blogCategoryId = categoryId;

                await this.assignmentRepository.save(assignment);
            }

            if (idsToRemove.length > 0 || idsToAssign.length > 0) {
                await this.loadAssignments();
            }
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.blog.list' });
        },

        onOpenDeleteModal(): void {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal(): void {
            this.showDeleteModal = false;
        },

        onDelete(): void {
            this.showDeleteModal = false;

            const id = this.effectiveId;

            if (!id) {
                return;
            }

            void this.blogRepository
                .delete(id)
                .then(() => {
                    this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageDeleteSuccess') });

                    return this.$router.push({ name: 'sw.blog.list' });
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                });
        },

        /**
         * The language switch asks before it discards anything: an entry is translated per language,
         * so switching reloads the entity and would silently drop what was typed.
         */
        abortOnLanguageChange(): boolean {
            return this.blog ? this.blogRepository.hasChanges(this.blog) : false;
        },

        saveOnLanguageChange(): Promise<void> {
            return this.onSave(false);
        },

        onChangeLanguage(languageId: EntitySchema.EntityKey<'language'>): void {
            this.setApiLanguageId(languageId);
            void this.loadBlog();
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
