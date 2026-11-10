/**
 * @sw-package discovery
 */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import { getContentLayoutNameKey } from 'src/app/utils/content-layout-label.util';
import { getBlogDefaultLayoutId } from '../../service/blog-layout.util';
import template from './sw-blog-detail-layout.html.twig';
import './sw-blog-detail-layout.scss';

const { Criteria } = Shopwell.Data;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl', 'systemConfigApiService'],

    props: {
        blog: {
            type: Object as PropType<EntitySchema.Entity<'blog'>>,
            required: false,
            default: null,
        },
        category: {
            type: Object as PropType<EntitySchema.Entity<'blog_category'>>,
            required: false,
            default: null,
        },
        isDisabled: {
            type: Boolean,
            default: false,
        },
    },

    data(): {
        configValues: Record<string, unknown>;
        currentLayout: EntitySchema.Entity<'content_layout'> | null;
        isLoading: boolean;
        isCopying: boolean;
        defaultsLoadFailed: boolean;
        layoutLoadFailed: boolean;
        isLoadingDefaults: boolean;
        copyFailed: boolean;
        hasCopied: boolean;
    } {
        return {
            configValues: {},
            currentLayout: null,
            isLoading: false,
            isCopying: false,
            defaultsLoadFailed: false,
            layoutLoadFailed: false,
            isLoadingDefaults: true,
            copyFailed: false,
            hasCopied: false,
        };
    },

    computed: {
        loadFailed(): boolean {
            return this.defaultsLoadFailed || this.layoutLoadFailed;
        },

        layoutRepository(): Repository<'content_layout'> {
            return this.repositoryFactory.create('content_layout');
        },

        layoutCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('rootSource', this.category ? 'none' : 'blog'));
            return criteria;
        },

        legacyLayoutCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('rootSource', 'none'));
            return criteria;
        },

        defaultAssignment(): EntitySchema.Entity<'blog_content_layout'> | null {
            return this.blog?.contentLayouts?.find((assignment) => !assignment.salesChannelId) ?? null;
        },

        selectedLayoutId(): EntitySchema.EntityKey<'content_layout'> | null {
            return this.category?.contentLayoutId ?? this.defaultAssignment?.contentLayoutId ?? null;
        },

        isLegacyLayout(): boolean {
            return !this.category && !this.selectedLayoutId && !!this.blog?.contentLayoutId;
        },

        defaultLayoutId(): EntitySchema.EntityKey<'content_layout'> | null {
            return getBlogDefaultLayoutId(this.configValues, this.category ? 'listing' : (this.blog?.type ?? 'post'));
        },

        effectiveLayoutId(): EntitySchema.EntityKey<'content_layout'> | null {
            return this.selectedLayoutId ?? this.blog?.contentLayoutId ?? this.defaultLayoutId;
        },

        isUsingDefault(): boolean {
            return !this.selectedLayoutId && !this.isLegacyLayout;
        },

        layoutName(): string {
            return this.currentLayout
                ? this.getLayoutName(this.currentLayout)
                : this.$t(this.loadFailed ? 'sw-blog.layout.loadError' : 'sw-blog.layout.noLayout');
        },

        assignmentLabel(): string {
            return this.$t(this.category ? 'sw-blog.layout.categoryAssignment' : 'sw-blog.layout.articleAssignment');
        },

        sourceLabel(): string {
            if (this.isLegacyLayout) {
                return this.$t('sw-blog.layout.sourceLegacy');
            }
            return this.$t(this.isUsingDefault ? 'sw-blog.layout.sourceDefault' : 'sw-blog.layout.sourceSpecific');
        },

        canEditLayout(): boolean {
            return !!this.currentLayout && this.acl.can('experience_studio.editor');
        },

        canCopyLayout(): boolean {
            return !this.isDisabled && !!this.currentLayout && this.acl.can('experience_studio.creator');
        },
    },

    watch: {
        effectiveLayoutId: {
            immediate: true,
            handler(): void {
                void this.loadCurrentLayout();
            },
        },
    },

    created(): void {
        void this.loadDefaults();
    },

    methods: {
        getLayoutName(layout: { id: string; name: string }): string {
            const key = getContentLayoutNameKey(layout);
            return key ? this.$t(key) : layout.name;
        },

        async loadDefaults(): Promise<void> {
            try {
                this.configValues = (await this.systemConfigApiService.getValues('core.content_system', null)) as Record<
                    string,
                    unknown
                >;
            } catch {
                this.defaultsLoadFailed = true;
            } finally {
                this.isLoadingDefaults = false;
            }
        },

        async loadCurrentLayout(): Promise<void> {
            const id = this.effectiveLayoutId;
            this.currentLayout = null;
            this.layoutLoadFailed = false;
            this.isLoading = false;
            if (!id) {
                return;
            }

            this.isLoading = true;
            try {
                const layout = await this.layoutRepository.get(id, Shopwell.Context.api);
                if (id === this.effectiveLayoutId) {
                    this.currentLayout = layout;
                    this.layoutLoadFailed = !layout;
                }
            } catch {
                if (id === this.effectiveLayoutId) {
                    this.layoutLoadFailed = true;
                }
            } finally {
                if (id === this.effectiveLayoutId) {
                    this.isLoading = false;
                }
            }
        },

        onLayoutChange(layoutId: EntitySchema.EntityKey<'content_layout'> | null): void {
            if (this.isDisabled || this.isCopying) {
                return;
            }
            this.hasCopied = false;
            this.copyFailed = false;
            if (this.category) {
                this.category.contentLayoutId = layoutId ?? undefined;
                return;
            }
            if (!this.blog?.contentLayouts) {
                return;
            }

            const assignment = this.defaultAssignment;
            if (!layoutId) {
                if (assignment) {
                    this.blog.contentLayouts.remove(assignment.id);
                }
                return;
            }

            if (assignment) {
                assignment.contentLayoutId = layoutId;
                return;
            }

            const repository = this.repositoryFactory.create('blog_content_layout');
            const created = repository.create(Shopwell.Context.api);
            created.blogId = this.blog.id;
            created.salesChannelId = undefined;
            created.contentLayoutId = layoutId;
            this.blog.contentLayouts.add(created);
        },

        onRestoreDefault(): void {
            if (this.isDisabled || this.isCopying) {
                return;
            }
            this.onLayoutChange(null);
            if (this.blog) {
                this.blog.contentLayoutId = undefined;
            }
        },

        async onCopyLayout(): Promise<void> {
            if (!this.canCopyLayout || this.isCopying || !this.effectiveLayoutId) {
                return;
            }
            this.copyFailed = false;
            const isLegacy = this.isLegacyLayout;
            const target = this.category ?? this.blog;
            const layoutId = this.effectiveLayoutId;
            this.isCopying = true;
            try {
                const copy = (await this.layoutRepository.clone(
                    this.effectiveLayoutId,
                    {
                        cloneChildren: false,
                        overwrites: { name: this.$t('sw-blog.layout.copyName', { name: this.layoutName }) },
                    },
                    Shopwell.Context.api,
                )) as { id: EntitySchema.EntityKey<'content_layout'> };
                if (this.isDisabled || target !== (this.category ?? this.blog) || layoutId !== this.effectiveLayoutId) {
                    return;
                }
                this.isCopying = false;
                if (isLegacy && this.blog) {
                    this.blog.contentLayoutId = copy.id;
                } else {
                    this.onLayoutChange(copy.id);
                }
                this.hasCopied = true;
            } catch {
                this.copyFailed = true;
            } finally {
                this.isCopying = false;
            }
        },

        onEditLayout(): void {
            if (!this.canEditLayout || !this.effectiveLayoutId || this.isDisabled || this.isCopying) {
                return;
            }

            void this.$router.push({
                name: 'sw.experience.studio.detail',
                params: { id: this.effectiveLayoutId },
                ...(!this.category && !this.isLegacyLayout && this.blog ? { query: { previewEntityId: this.blog.id } } : {}),
            });
        },
    },
});
