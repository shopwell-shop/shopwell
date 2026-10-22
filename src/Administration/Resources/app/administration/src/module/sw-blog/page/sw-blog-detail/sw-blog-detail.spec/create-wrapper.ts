/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import BlogApiService from '../../../service/blog.api.service';
import { assignments, blog } from './fixtures';

type Association = { association: string; criteria: { associations: Association[] } };

/**
 * The names of the associations a criteria reads. `addAssociation` stores an association object, not
 * the bare name, and a nested path becomes a nested association.
 *
 * @private
 */
export function associationNames(criteria: { associations: unknown }, prefix: string = ''): string[] {
    return (criteria.associations as Association[]).flatMap((association) => {
        const name = `${prefix}${association.association}`;

        return [name, ...associationNames(association.criteria, `${name}.`)];
    });
}

/**
 * The surface of the page the tests drive.
 *
 * @private
 */
export type DetailVm = {
    blog: Record<string, unknown> | null;
    video: Record<string, unknown> | null;
    cmsPage: Record<string, unknown> | null;
    assignments: Array<{ id: string; blogCategoryId: string | null }>;
    selectedCategoryIds: string[];
    categories: unknown[];
    pendingCommentCount: number;
    isLoading: boolean;
    isSaving: boolean;
    showDeleteModal: boolean;
    showCategoryModal: boolean;
    showAuthorModal: boolean;
    newCategoryName: string;
    newAuthorName: string;
    canEdit: boolean;
    canDelete: boolean;
    isNew: boolean;
    isTitleMissing: boolean;
    isCreateCategoryDisabled: boolean;
    isCreateAuthorDisabled: boolean;
    title: string;
    loadBlog: () => Promise<void>;
    ensureVideo: () => void;
    isCategorySelected: (categoryId: string) => boolean;
    onToggleCategory: (categoryId: string) => void;
    onOpenCategoryModal: () => void;
    onCreateCategory: () => void;
    onOpenAuthorModal: () => void;
    onCreateAuthor: () => void;
    onPublishNow: () => void;
    onClearSchedule: () => void;
    onCoverSelected: (media: Array<{ id: string }>) => void;
    onCoverRemoved: () => void;
    onSave: (redirect?: boolean) => Promise<void>;
    onCancel: () => void;
    onOpenDeleteModal: () => void;
    onCloseDeleteModal: () => void;
    onDelete: () => void;
    abortOnLanguageChange: () => boolean;
    saveOnLanguageChange: () => Promise<void>;
    onChangeLanguage: (languageId: string) => void;
};

/**
 * The mounted page, typed with the surface above.
 *
 * @private
 */
export type TypedWrapper = { vm: DetailVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
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
    } = {},
): Promise<{
    wrapper: TypedWrapper;
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

    const saveBlog = jest.fn(() => Promise.resolve());
    const deleteBlog = jest.fn(() => Promise.resolve());
    const getBlog = jest.fn(() => Promise.resolve({ ...entry }));
    const saveVideo = jest.fn(() => Promise.resolve());
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
            return { ...blog, id: 'blog-new', title: '', slug: '', content: null, categories: [], videos: [] };
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

    const cmsPageRepository = {
        get: jest.fn(() => Promise.resolve({ id: 'page-1', versionId: 'version-1' })),
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'cms_page'>>([]))),
    };

    const categoryRepository = {
        search: jest.fn(() => Promise.resolve(collection<EntityCollection<'blog_category'>>([]))),
        save: jest.fn(() => Promise.resolve()),
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

    const wrapper = mount(await wrapTestComponent('sw-blog-detail', { sync: true }), {
        props: { blogId },
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot><slot name="sidebar"></slot><slot></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot name="toolbar"></slot><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-sidebar': { template: '<div><slot></slot></div>' },
                'sw-sidebar-item': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-modal': { template: '<div class="modal"><slot></slot><slot name="modal-footer"></slot></div>' },
                'sw-language-switch': true,
                'sw-media-compact-upload-v2': true,
                'sw-entity-single-select': true,
                'sw-entity-multi-id-select': true,
                'sw-cms-page-form': true,
                'sw-blog-detail-content': true,
                'sw-blog-status-badge': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-select': true,
                'mt-switch': true,
                'mt-checkbox': true,
                'mt-badge': true,
                'mt-datepicker': true,
                'mt-button': true,
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

                        if (entityName === 'cms_page') {
                            return cmsPageRepository;
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
                $route: { name: 'sw.blog.detail', meta: { $module: { icon: 'regular-image-text' } } },
                $router: { push, replace },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return {
        wrapper: wrapper as unknown as TypedWrapper,
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
