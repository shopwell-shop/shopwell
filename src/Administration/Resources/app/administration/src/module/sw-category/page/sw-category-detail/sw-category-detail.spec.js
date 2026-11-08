/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-category/page/sw-category-detail', () => {
    const saveMock = jest.fn(() => Promise.resolve());
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-category-detail', { sync: true }), {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                    <div>
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                        <slot name="side-content"></slot>
                    </div>`,
                    },
                    'sw-category-tree': {
                        template: '<div class="sw-category-tree"></div>',
                        props: ['allowEdit', 'allowCreate', 'allowDelete'],
                    },
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot></slot></div>',
                        props: ['disabled'],
                    },
                    'sw-sidebar-collapse': {
                        template: `
                    <div class="sw-sidebar-collapse">
                        <slot name="header"></slot>
                        <slot name="actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                    },
                    'sw-collapse': await wrapTestComponent('sw-collapse'),
                    'sw-landing-page-tree': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-skeleton': true,
                    'sw-category-view': true,
                    'sw-category-entry-point-overwrite-modal': true,
                    'sw-landing-page-view': true,
                    'sw-discard-changes-modal': true,
                    'sw-empty-state': true,
                },
                provide: {
                    customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },

                    repositoryFactory: {
                        create: () => ({
                            search: () =>
                                Promise.resolve({
                                    get: () => ({ sections: [] }),
                                }),
                            save: saveMock,
                            get: () =>
                                Promise.resolve({
                                    navigationSalesChannels: [],
                                    footerSalesChannels: [],
                                    serviceSalesChannels: [],
                                }),
                        }),
                    },
                    seoUrlService: {},
                },
            },
        });
    }

    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should not allow to modify', async () => {
        const wrapper = await createWrapper();

        Shopwell.Store.get('swCategoryDetail').category = {};

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(true);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(false);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to edit', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        Shopwell.Store.get('swCategoryDetail').category = {};

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor'];

        const wrapper = await createWrapper();

        Shopwell.Store.get('swCategoryDetail').category = {};

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = ['category.creator', 'category.editor', 'category.deleter'];

        const wrapper = await createWrapper();

        Shopwell.Store.get('swCategoryDetail').category = {};

        await wrapper.setData({
            isLoading: false,
        });

        const saveButton = wrapper.getComponent('.sw-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.sw-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(true);
    });

    it('should load landing page translations', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.landingPageCriteria.associations.some(({ association }) => association === 'translations')).toBe(
            true,
        );
    });
});
