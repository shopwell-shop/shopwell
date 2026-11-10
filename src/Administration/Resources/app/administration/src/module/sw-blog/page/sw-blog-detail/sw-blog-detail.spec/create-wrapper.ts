/**
 * @sw-package discovery
 */
import { mount, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { Router } from 'vue-router';
import BlogApiService from '../../../service/blog.api.service';
import { assignments, blog } from './fixtures';

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

function entityCollection(entity: keyof EntitySchema.Entities, items: unknown) {
    return new Shopwell.Data.EntityCollection(
        `/${entity.replaceAll('_', '-')}`,
        entity,
        Shopwell.Context.api,
        null,
        items as never[],
    );
}

let activeWrapper: VueWrapper | null = null;

/**
 * Unmounts the page of the previous test.
 *
 * A mounted `sw-page` keeps listening on the global router, so a test that leaves one behind changes
 * the behavior of the next one.
 *
 * @private
 */
export function unmountActiveWrapper(): void {
    activeWrapper?.unmount();
    activeWrapper = null;
}

/**
 * Mounts the page against repositories that answer from the fixtures above.
 *
 * `blogId: null` mounts the page the create route opens - the one that writes a new entry.
 *
 * @private
 */
export async function createWrapper(
    options: {
        can?: (privilege: string) => boolean;
        entry?: Record<string, unknown>;
        hasChanges?: boolean;
        blogId?: string | null;
        blogType?: string;
        realRouter?: boolean;
    } = {},
): Promise<{
    wrapper: VueWrapper;
    router: Router | null;
    getBlog: jest.Mock;
    saveBlog: jest.Mock;
    deleteBlog: jest.Mock;
    saveVideo: jest.Mock;
    saveAssignment: jest.Mock;
    deleteAssignment: jest.Mock;
    push: jest.Mock;
    replace: jest.Mock;
}> {
    const { can = () => true, entry = blog, hasChanges = false, blogId = 'blog-1' } = options;

    const saveBlog = jest.fn<Promise<void>, [Record<string, unknown>]>(() => Promise.resolve());
    const deleteBlog = jest.fn(() => Promise.resolve());
    const getBlog = jest.fn(() =>
        Promise.resolve({
            ...entry,
            categories: entityCollection(
                'blog_category_assignment',
                (entry.categories as unknown[])?.length ? entry.categories : assignments,
            ),
            videos: entityCollection('blog_video', entry.videos ?? []),
            tags: entityCollection('tag', []),
            contentLayouts: entityCollection('blog_content_layout', []),
        }),
    );
    const saveVideo = jest.fn<Promise<void>, [Record<string, unknown>]>(() => Promise.resolve());
    const saveAssignment = jest.fn(() => Promise.resolve());
    const deleteAssignment = jest.fn(() => Promise.resolve());
    const push = jest.fn(() => Promise.resolve());
    const replace = jest.fn(() => Promise.resolve());

    const blogRepository = {
        get: getBlog,
        save: saveBlog,
        delete: deleteBlog,
        hasChanges: jest.fn(() => hasChanges),
        create: () => {
            return {
                ...blog,
                id: 'blog-new',
                title: '',
                slug: '',
                content: null,
                categories: entityCollection('blog_category_assignment', []),
                videos: entityCollection('blog_video', []),
                tags: entityCollection('tag', []),
                contentLayouts: entityCollection('blog_content_layout', []),
            };
        },
    };

    const videoRepository = {
        get: jest.fn(() => Promise.resolve(null)),
        save: saveVideo,
        delete: jest.fn(() => Promise.resolve()),
        create: () => {
            return {
                id: 'video-new',
                blogId: null,
                videoUrl: '',
                durationSeconds: null,
                posterMediaId: null,
                provider: null,
            };
        },
    };

    const assignmentRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category_assignment'>>(assignments))),
        save: saveAssignment,
        delete: deleteAssignment,
        create: () => {
            return { id: 'assignment-new', blogId: null, blogCategoryId: null };
        },
    };

    const contentLayoutRepository = {
        get: jest.fn(() => Promise.resolve({ id: 'page-1', versionId: 'version-1' })),
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'content_layout'>>([]))),
    };

    const categories: Array<Record<string, unknown>> = [
        { id: 'category-1', name: 'First' },
        { id: 'category-2', name: 'Second' },
    ];
    const categoryRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category'>>(categories))),
        save: jest.fn((category: Record<string, unknown>) => {
            categories.push(category);
            return Promise.resolve();
        }),
        create: () => {
            return { id: 'category-new', name: null, slug: null, active: true };
        },
    };

    const authorRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_author'>>([]))),
        save: jest.fn(() => Promise.resolve()),
        create: () => {
            return { id: 'author-new', name: null, active: true };
        },
    };

    const commentRepository = {
        search: jest.fn(() => Promise.resolve(Object.assign([], { total: 2 }))),
    };

    const component = await wrapTestComponent('sw-blog-detail', { sync: true });
    const router = options.realRouter
        ? createRouter({
              history: createMemoryHistory(),
              routes: [
                  { path: '/article', name: 'sw.blog.detail', component, props: { blogId } },
                  { path: '/outside', component: { template: '<p class="outside">Outside</p>' } },
              ],
          })
        : null;
    if (router) {
        await router.push('/article');
        await router.isReady();
    }
    const render = router ? mount : shallowMount;
    const wrapper = render(router ? { template: '<router-view />' } : component, {
        ...(router ? {} : { props: { blogId, blogType: options.blogType } }),
        global: {
            plugins: router ? [router] : [],
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="language-switch"></slot><slot name="content"></slot><slot name="sidebar"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot name="toolbar"></slot><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-sidebar': { template: '<div><slot></slot></div>' },
                'sw-sidebar-item': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-language-switch': {
                    name: 'sw-language-switch',
                    props: ['saveChangesFunction', 'abortChangeFunction', 'disabled'],
                    data: () => ({ saveFailed: false }),
                    template:
                        '<section><output class="language-save-failed">{{ saveFailed }}</output><output class="unsaved">{{ abortChangeFunction() }}</output><button class="language-save" @click="saveChangesFunction().catch(() => saveFailed = true)">Save translation</button><button class="language-change" @click="$emit(\'on-change\', \'language-2\')">Change language</button></section>',
                },
                'sw-media-compact-upload-v2': {
                    props: ['source'],
                    template:
                        '<section><output class="cover-source">{{ source }}</output><button class="select-cover" @click="$emit(\'selection-change\', [{ id: \'media-1\' }])">Select cover</button><button class="remove-cover" @click="$emit(\'delete-item\')">Remove cover</button></section>',
                },
                'sw-entity-single-select': {
                    props: ['value', 'disabled'],
                    template:
                        '<input :value="value" :disabled="disabled" @input="$emit(\'update:value\', $event.target.value)">',
                },
                'sw-entity-multi-id-select': {
                    name: 'sw-entity-multi-id-select',
                    props: ['value'],
                    template: `<button class="select-category" @click="$emit('update:value', ['category-2'])">Select category</button>`,
                },
                'sw-blog-detail-layout': true,
                'sw-entity-tag-select': true,
                'mt-popover': {
                    template:
                        '<div><slot name="trigger" :toggleFloatingUi="() => {}"></slot><slot name="popover-items__base" :toggleFloatingUi="() => {}"></slot></div>',
                },
                'mt-popover-item': { props: ['onLabelClick'], template: '<button @click="onLabelClick()"></button>' },
                'sw-discard-changes-modal': await wrapTestComponent('sw-discard-changes-modal', { sync: true }),
                'sw-context-menu-item': { template: '<button><slot></slot></button>' },
                'sw-content-layout-form': {
                    props: ['page'],
                    template: '<output class="content-layout-id">{{ page.id }}</output>',
                },
                'sw-blog-detail-content': {
                    props: ['blog', 'video', 'isDisabled'],
                    template:
                        '<section class="content-editor"><output class="video-id">{{ video?.id }}</output><output class="video-blog">{{ video?.blogId }}</output><output class="video-provider">{{ video?.provider }}</output></section>',
                },
                'sw-blog-status-badge': true,
                'mt-text-field': {
                    props: ['modelValue', 'disabled', 'error'],
                    template:
                        '<input :value="modelValue" :disabled="disabled" :data-error="error?.code" @input="$emit(\'update:modelValue\', $event.target.value)">',
                },
                'mt-textarea': true,
                'mt-select': {
                    props: ['modelValue', 'disabled', 'options'],
                    template:
                        '<select :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                },
                'mt-switch': true,
                'mt-checkbox': {
                    props: ['checked', 'label', 'disabled'],
                    template:
                        '<label>{{ label }}<input type="checkbox" :checked="checked" :disabled="disabled" @change="$emit(\'update:checked\', $event.target.checked)"></label>',
                },
                'mt-badge': { template: '<span><slot></slot></span>' },
                'mt-tabs': {
                    props: ['items'],
                    template: `<div><button v-for="item in items" :data-tab="item.name" @click="$emit('new-item-active', item.name)">{{ item.label }}</button></div>`,
                },
                'mt-datepicker': true,
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot></slot></button>' },
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        if (entityName === 'blog_video') {
                            return videoRepository;
                        }

                        if (entityName === 'blog_category_assignment') {
                            return assignmentRepository;
                        }

                        if (entityName === 'content_layout') {
                            return contentLayoutRepository;
                        }

                        if (entityName === 'blog_category') {
                            return categoryRepository;
                        }

                        if (entityName === 'blog_author') {
                            return authorRepository;
                        }

                        if (entityName === 'blog_comment') {
                            return commentRepository;
                        }

                        return blogRepository;
                    },
                },
                acl: { can },
                blogApiService: new BlogApiService(),
            },
            mocks: {
                ...(router
                    ? { $router: router, $route: router.currentRoute.value }
                    : {
                          $route: { name: 'sw.blog.detail', meta: { $module: { icon: 'regular-image-text' } } },
                          $router: { push, replace },
                      }),
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return {
        wrapper,
        router,
        getBlog,
        saveBlog,
        deleteBlog,
        saveVideo,
        saveAssignment,
        deleteAssignment,
        push,
        replace,
    };
}
