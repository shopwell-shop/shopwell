/**
 * @sw-package discovery
 */
import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-blog-detail.html.twig';
import './sw-blog-detail.scss';
import { blogApi } from '../../service/blog.api.service';
import { getBlogTypeDefinition, registeredBlogTypes } from '../../service/blog-type.registry';

const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

type Blog = Omit<EntitySchema.Entity<'blog'>, 'publishedAt'> & { publishedAt?: EntitySchema.DateTime | null };
type BlogRepository = Omit<RepositoryType<'blog'>, 'save' | 'hasChanges'> & {
    save(entity: Blog): ReturnType<RepositoryType<'blog'>['save']>;
    hasChanges(entity: Blog): boolean;
};
type BlogVideo = EntitySchema.Entity<'blog_video'>;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl', 'blogApiService'],
    mixins: [Shopwell.Mixin.getByName('notification')],

    props: {
        blogId: { type: String, required: false, default: null },
        blogType: { type: String, required: false, default: 'post' },
    },

    data() {
        return {
            blog: null as Blog | null,
            video: null as BlogVideo | null,
            selectedCategoryIds: [] as Array<EntitySchema.EntityKey<'blog_category'>>,
            originalCategoryIds: [] as Array<EntitySchema.EntityKey<'blog_category'>>,
            persistedId: null as EntitySchema.EntityKey<'blog'> | null,
            activeTab: 'content',
            isLoading: false,
            isSaving: false,
            mediaUploadTag: 'sw-blog-detail-cover',
            showDeleteModal: false,
            pendingRoute: null as string | null,
            allowNavigation: false,
        };
    },

    computed: {
        ...mapPropertyErrors('blog', [
            'title',
            'slug',
            'teaser',
            'metaTitle',
            'metaDescription',
            'keywords',
            'tags',
        ]),
        blogRepository(): BlogRepository {
            return this.repositoryFactory.create('blog', '', { useSync: true });
        },
        assignmentRepository(): RepositoryType<'blog_category_assignment'> {
            return this.repositoryFactory.create('blog_category_assignment');
        },
        categoryRepository(): RepositoryType<'blog_category'> {
            return this.repositoryFactory.create('blog_category');
        },
        videoRepository(): RepositoryType<'blog_video'> {
            return this.repositoryFactory.create('blog_video');
        },
        effectiveId(): EntitySchema.EntityKey<'blog'> | null {
            return this.blog?.id ?? (this.blogId as EntitySchema.BlogId | null);
        },
        isNew(): boolean {
            return !this.blogId && !this.persistedId;
        },
        isBusy(): boolean {
            return this.isLoading || this.isSaving;
        },
        authorCriteria(): CriteriaType {
            const { Criteria } = Shopwell.Data;
            const active = Criteria.equals('active', true);
            return new Criteria(1, 25).addFilter(
                this.blog?.authorId ? Criteria.multi('OR', [active, Criteria.equals('id', this.blog.authorId)]) : active,
            );
        },
        canEdit(): boolean {
            return this.acl.can('blog.editor');
        },
        canDelete(): boolean {
            return !this.isNew && this.acl.can('blog.deleter');
        },
        tabItems(): Array<{ name: string; label: string }> {
            return [
                { name: 'content', label: this.$t('sw-blog.detail.cardContent') },
                { name: 'layout', label: this.$t('sw-blog.detail.cardLayout') },
                { name: 'seo', label: this.$t('sw-blog.detail.cardSeo') },
            ];
        },
        typeLabel(): string {
            const type = this.blog?.type ?? this.blogType;
            const key = getBlogTypeDefinition(type)?.label ?? blogApi(this).typeLabelKey(type);
            return this.$te(key) ? this.$t(key) : (getBlogTypeDefinition(type)?.label ?? type);
        },
        headerTitle(): string {
            return this.blog?.title?.trim() || this.$t('sw-blog.list.defaultTitle');
        },
        deleteMessage(): string {
            return this.$t('sw-blog.list.textDeleteBlog', { name: this.blog?.title ?? '' });
        },
        hasChanges(): boolean {
            if (!this.blog) {
                return false;
            }
            return (
                this.blogRepository.hasChanges(this.blog) ||
                [...this.selectedCategoryIds].sort().join(',') !== [...this.originalCategoryIds].sort().join(',')
            );
        },
    },

    created() {
        void this.loadBlog();
    },

    beforeRouteLeave(to: RouteLocationNormalized, from: RouteLocationNormalized, next: NavigationGuardNext) {
        if (this.hasChanges && !this.isSaving && !this.allowNavigation) {
            this.pendingRoute = to.fullPath;
            next(false);
            return;
        }
        next();
    },

    methods: {
        async loadBlog(): Promise<void> {
            this.isLoading = true;
            try {
                const id = (this.blogId ?? this.persistedId) as EntitySchema.EntityKey<'blog'> | null;
                if (id) {
                    this.blog = await this.blogRepository.get(id, Shopwell.Context.api, blogApi(this).detailCriteria(id));
                } else {
                    if (!Shopwell.Store.get('context').isSystemDefaultLanguage) {
                        Shopwell.Store.get('context').resetLanguageToDefault();
                    }
                    this.blog = this.blogRepository.create();
                    this.blog.type = registeredBlogTypes().includes(this.blogType) ? this.blogType : 'post';
                    this.blog.active = false;
                    this.blog.publishedAt = null;
                }
                this.selectedCategoryIds = [...(this.blog?.categories ?? [])].map((row) => row.blogCategoryId);
                this.originalCategoryIds = [...this.selectedCategoryIds];
                this.ensureVideo();
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageLoadError') });
            } finally {
                this.isLoading = false;
            }
        },

        ensureVideo(): void {
            if (!this.blog || this.blog.type !== 'video') {
                this.video = null;
                return;
            }
            const videos = this.blog.videos;
            if (!videos) {
                return;
            }
            this.video = videos[0] ?? this.videoRepository.create();
            if (!videos.has(this.video.id)) {
                this.video.blogId = this.blog.id;
                this.video.videoUrl = '';
                this.video.provider = 'youtube';
                videos.add(this.video);
            }
        },

        updateCategories(): void {
            const blog = this.blog;
            const categories = blog?.categories;
            if (!blog || !categories) {
                return;
            }
            const assignments = [...categories];
            const removed = blogApi(this).assignmentIdsToRemove(assignments, this.selectedCategoryIds);
            const added = blogApi(this).categoryIdsToAssign(assignments, this.selectedCategoryIds);
            removed.forEach((id) => categories.remove(id));
            added.forEach((categoryId) => {
                const assignment = this.assignmentRepository.create();
                assignment.blogId = blog.id;
                assignment.blogCategoryId = categoryId;
                categories.add(assignment);
            });
        },

        async onSave(redirect = false): Promise<boolean> {
            if (!this.blog || this.isBusy || !this.canEdit) {
                return false;
            }
            if (!this.blog.title?.trim()) {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageTitleRequired') });
                return false;
            }
            if (!this.blog.slug?.trim()) {
                if (!this.isNew) {
                    this.createNotificationError({ message: this.$t('sw-blog.detail.messageSlugRequired') });
                    return false;
                }
                this.blog.slug = blogApi(this).slugFor(this.blog.title, this.$t('sw-blog.list.defaultSlug'));
            }
            this.isSaving = true;
            try {
                this.updateCategories();
                await this.blogRepository.save(this.blog);
                const wasNew = this.isNew;
                this.persistedId = this.blog.id;
                await this.loadBlog();
                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageSaveSuccess') });
                if (redirect) {
                    await this.$router.push({ name: 'sw.blog.list' });
                } else if (wasNew) {
                    await this.$router.replace({ name: 'sw.blog.detail', params: { id: this.persistedId } });
                }
                return true;
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        onPublishNow(): void {
            if (!this.blog || this.isBusy || !this.canEdit) {
                return;
            }
            this.blog.active = true;
            this.blog.publishedAt = new Date().toISOString() as EntitySchema.DateTime;
            void this.onSave();
        },
        onClearSchedule(): void {
            if (!this.blog || this.isBusy || !this.canEdit) {
                return;
            }
            this.blog.active = false;
            this.blog.publishedAt = null;
            void this.onSave();
        },
        onCoverSelected(media: Array<Pick<EntitySchema.Entity<'media'>, 'id'>>): void {
            this.setCover(media[0]?.id ?? null);
        },
        onCoverRemoved(): void {
            this.setCover(null);
        },
        setCover(mediaId: EntitySchema.EntityKey<'media'> | null): void {
            if (this.blog && this.canEdit && !this.isBusy) {
                this.blog.coverMediaId = mediaId as EntitySchema.EntityKey<'media'>;
            }
        },
        onOpenDeleteModal(): void {
            if (!this.canDelete || this.isBusy) {
                return;
            }
            this.showDeleteModal = true;
        },
        onCloseDeleteModal(): void {
            this.showDeleteModal = false;
        },
        async onDelete(): Promise<void> {
            if (!this.effectiveId || !this.canDelete || this.isBusy) {
                return;
            }
            this.showDeleteModal = false;
            try {
                await this.blogRepository.delete(this.effectiveId);
                this.allowNavigation = true;
                this.createNotificationSuccess({ message: this.$t('sw-blog.detail.messageDeleteSuccess') });
                await this.$router.push({ name: 'sw.blog.list' });
            } catch {
                this.createNotificationError({ message: this.$t('sw-blog.detail.messageSaveError') });
            }
        },
        onDiscardChanges(): void {
            this.allowNavigation = true;
            if (this.pendingRoute) {
                void this.$router.push(this.pendingRoute);
            }
        },
        abortOnLanguageChange(): boolean {
            return this.hasChanges;
        },
        async saveOnLanguageChange(): Promise<void> {
            if (!(await this.onSave())) {
                throw new Error('Blog save failed');
            }
        },
        onChangeLanguage(languageId: EntitySchema.EntityKey<'language'>): void {
            Shopwell.Store.get('context').setApiLanguageId(languageId);
            void this.loadBlog();
        },
    },
});
