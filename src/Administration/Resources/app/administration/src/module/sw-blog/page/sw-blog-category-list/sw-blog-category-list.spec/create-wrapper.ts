/**
 * @sw-package discovery
 */
import { mount, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import BlogApiService from '../../../service/blog.api.service';

/** @private */
export function collection<T>(items: unknown[]): T {
    return new Shopwell.Data.EntityCollection(
        '/blog',
        'blog',
        Shopwell.Context.api,
        null,
        items as EntitySchema.Entity<'blog'>[],
        items.length,
    ) as unknown as T;
}

/** @private */
export const category = {
    id: 'category-1',
    name: 'Guides',
    slug: 'guides',
    description: null,
    active: true,
    contentLayoutId: 'page-1',
};

const entries = [
    { id: 'blog-1', title: 'Autumn guide', publishedAt: null, updatedAt: '2026-10-01T10:00:00.000Z' },
    {
        id: 'blog-2',
        title: 'Behind the scenes',
        publishedAt: '2026-10-02T10:00:00.000Z',
        updatedAt: '2026-10-02T10:00:00.000Z',
    },
];

let activeWrapper: VueWrapper | null = null;

/** @private */
export async function createWrapper(
    options: {
        categoryId?: string | null;
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
        hasChanges?: boolean;
        entryTotal?: number;
        realRouter?: boolean;
    } = {},
): Promise<{
    wrapper: VueWrapper;
    router: Router | null;
    getCategory: jest.Mock;
    saveCategory: jest.Mock;
    deleteCategory: jest.Mock;
    getContentLayout: jest.Mock;
    push: jest.Mock;
    blogSearch: jest.Mock;
    searchAssignments: jest.Mock;
    deleteAssignment: jest.Mock;
}> {
    const { can = () => true, entry = category, hasChanges = false } = options;

    const assignments = new Shopwell.Data.EntityCollection(
        '/blog-category-assignment',
        'blog_category_assignment',
        Shopwell.Context.api,
        null,
        [],
    );
    const getCategory = jest.fn(() => Promise.resolve({ ...entry, assignments }));
    const saveCategory = jest.fn(() => Promise.resolve());
    const deleteCategory = jest.fn(() => Promise.resolve());
    const getContentLayout = jest.fn(() => Promise.resolve({ id: 'page-1', versionId: 'version-1' }));
    const push = jest.fn(() => Promise.resolve());

    const categoryRepository = {
        get: getCategory,
        save: saveCategory,
        delete: deleteCategory,
        hasChanges: jest.fn(() => hasChanges),
        create: () => ({ id: 'category-new', name: '', slug: '', active: true }),
    };

    const contentLayoutRepository = {
        get: getContentLayout,
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'content_layout'>>([]))),
    };

    const blogSearch = jest.fn(() =>
        Promise.resolve(
            Object.assign(collection<EntityCollection<'blog'>>(entries), { total: options.entryTotal ?? entries.length }),
        ),
    );
    const blogRepository = { search: blogSearch };
    const deleteAssignment = jest.fn(() => Promise.resolve());
    const searchAssignments = jest.fn((criteria: { filters: Array<{ field: string; value: string }> }) => {
        const blogId = criteria.filters.find((filter) => filter.field === 'blogId')?.value;
        return Promise.resolve(
            collection<EntityCollection<'blog_category_assignment'>>(
                blogId === 'blog-1' ? [{ id: 'assignment-1', blogId, blogCategoryId: 'category-1' }] : [],
            ),
        );
    });

    const component = await wrapTestComponent('sw-blog-category-list', { sync: true });
    const router = options.realRouter
        ? createRouter({
              history: createMemoryHistory(),
              routes: [
                  {
                      path: '/category',
                      name: 'sw.blog.category.list',
                      component,
                      props: (route) => ({
                          categoryId:
                              typeof route.query.id === 'string'
                                  ? route.query.id
                                  : options.categoryId === undefined
                                    ? 'category-1'
                                    : options.categoryId,
                      }),
                  },
                  { path: '/outside', name: 'outside', component: { template: '<p class="outside">Outside</p>' } },
              ],
          })
        : null;
    if (router) {
        await router.push('/category');
        await router.isReady();
    }
    const render = router ? mount : shallowMount;
    const wrapper = render(router ? { template: '<router-view />' } : component, {
        ...(router ? {} : { props: { categoryId: options.categoryId === undefined ? 'category-1' : options.categoryId } }),
        global: {
            plugins: router ? [router] : [],
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="search-bar"></slot><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="language-switch"></slot><slot name="side-content"></slot><slot name="content"></slot></div>',
                },
                'sw-search-bar': {
                    props: ['initialSearch'],
                    template: '<input class="category-search" @input="$emit(\'search\', $event.target.value)">',
                },
                'sw-blog-detail-layout': {
                    props: ['category', 'isDisabled'],
                    template: '<output>{{ category.contentLayoutId }}</output>',
                },
                'sw-context-menu-item': { template: '<button><slot /></button>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-blog-category-navigation': {
                    name: 'sw-blog-category-navigation',
                    methods: {
                        onOpenCreate(this: {
                            $emit: (
                                event: string,
                                placement: { parentId: string | null; afterCategoryId: string | null },
                            ) => void;
                        }) {
                            this.$emit('create', { parentId: null, afterCategoryId: 'category-1' });
                        },
                    },
                    props: [
                        'selectedId',
                        'refreshKey',
                        'beforeStructureChange',
                        'searchTerm',
                    ],
                    data: () => ({ approved: null }),
                    template:
                        '<section><button class="select-category" :data-refresh="refreshKey" @click="$emit(\'select\', \'category-2\')">Select</button><button class="change-structure" @click="beforeStructureChange().then(result => approved = result)">Move category</button><button class="create-child" @click="$emit(\'create\', { parentId: \'category-1\', afterCategoryId: null })">Create child</button><button class="start-tree-move" @click="$emit(\'structure-busy\', true)">Moving</button><button class="finish-tree-move" @click="$emit(\'structure-busy\', false)">Moved</button><output class="structure-approved">{{ approved }}</output></section>',
                },
                'mt-tabs': {
                    props: ['items'],
                    template:
                        '<div><button v-for="item in items" :key="item.name" :data-tab="item.name" @click="$emit(\'new-item-active\', item.name)">{{ item.label }}</button></div>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template:
                        '<div><div v-for="item in dataSource" :key="item.id"><span>{{ item.title }}</span><slot name="column-publishedAt" :item="item" /><slot name="column-updatedAt" :item="item" /><slot name="actions" :item="item" /></div></div>',
                },
                'sw-time-ago': { props: ['date'], template: '<time :datetime="date" />' },
                'sw-blog-status-badge': true,
                'mt-card': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-pagination': {
                    props: ['page', 'total'],
                    template:
                        '<button class="next-entry-page" @click="$emit(\'page-change\', { page: 6, limit: 25 })">More</button>',
                },
                'sw-modal': { template: '<div class="modal"><slot /><slot name="modal-footer" /></div>' },
                'sw-language-switch': {
                    props: ['saveChangesFunction', 'abortChangeFunction'],
                    data: () => ({ saveFailed: false }),
                    template:
                        '<section><output class="unsaved">{{ abortChangeFunction() }}</output><output class="language-save-failed">{{ saveFailed }}</output><button class="language-save" @click="saveChangesFunction().catch(() => saveFailed = true)">Save translation</button><button class="language-change" @click="$emit(\'on-change\', \'language-2\')">Change language</button></section>',
                },
                'sw-entity-single-select': {
                    props: ['value', 'disabled'],
                    template:
                        '<input :value="value" :disabled="disabled" @input="$emit(\'update:value\', $event.target.value, { id: $event.target.value, title: \'Video\', type: \'video\' })">',
                },
                'mt-text-field': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)">',
                },
                'mt-textarea': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<textarea :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)">',
                },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        if (entityName === 'content_layout') {
                            return contentLayoutRepository;
                        }

                        if (entityName === 'blog_category_assignment') {
                            return {
                                create: () => ({ id: 'assignment-new' }),
                                search: searchAssignments,
                                delete: deleteAssignment,
                            };
                        }

                        if (entityName === 'blog') {
                            return blogRepository;
                        }

                        return categoryRepository;
                    },
                },
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                ...(!router
                    ? {
                          $route: { name: 'sw.blog.category.list', meta: { $module: { icon: 'regular-image-text' } } },
                          $router: { push, replace: jest.fn(() => Promise.resolve()) },
                      }
                    : {}),
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return {
        wrapper,
        router,
        getCategory,
        saveCategory,
        deleteCategory,
        getContentLayout,
        push,
        blogSearch,
        searchAssignments,
        deleteAssignment,
    };
}

/** @private */
export function cleanupWrapper(): void {
    activeWrapper?.unmount();
    activeWrapper = null;
    jest.restoreAllMocks();
}
