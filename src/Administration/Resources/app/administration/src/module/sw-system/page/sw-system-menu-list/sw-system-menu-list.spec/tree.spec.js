/**
 * @sw-package fundamentals@framework
 */
import { createWrapper, menuStore, treeStub } from './create-wrapper';

describe('module/sw-system/page/sw-system-menu-list (tree)', () => {
    let wrapper;

    beforeEach(() => {
        Shopwell.Context.api.languageId = 'language-en';
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }

        jest.restoreAllMocks();
    });

    it('loads the stored navigation once the page is created', async () => {
        wrapper = await createWrapper();

        expect(menuStore().load).toHaveBeenCalled();
        expect(wrapper.find('.sw-system-menu-tree-stub').exists()).toBe(true);
    });

    it('uses the standard top search bar and removes the tree search', async () => {
        wrapper = await createWrapper();

        const searchBar = wrapper.findComponent('.sw-search-bar-stub');

        expect(searchBar.exists()).toBe(true);
        expect(searchBar.props('initialSearchType')).toBe('admin_menu_item');
        expect(searchBar.props('typeSearchAlwaysInContainer')).toBe(false);
        expect(wrapper.find('.sw-system-menu-list__tree-search').exists()).toBe(false);
    });

    it('renders the top level entries without expanding them', async () => {
        wrapper = await createWrapper();

        expect(treeStub(wrapper).props('items')).toHaveLength(1);
        expect(treeStub(wrapper).props('expandedIds')).toEqual([]);
        expect(treeStub(wrapper).props('selectedId')).toBeNull();
    });

    it('resolves the declared label of an entry that has no stored one', async () => {
        wrapper = await createWrapper();

        expect(treeStub(wrapper).props('items')[0].label).toBe('sw-catalogue.general.mainMenuItemProducts');
    });

    it('shows the stored label of the language the page displays', async () => {
        wrapper = await createWrapper();

        wrapper.findComponent('.sw-language-switch-stub').vm.$emit('on-change', 'language-zh');
        await flushPromises();

        const products = treeStub(wrapper).props('items')[0].children[0];

        expect(products.label).toBe('产品目录');
        expect(products.groupLabel).toBe('常规');
    });

    it('expands and collapses an entry', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.tree-toggle').trigger('click');
        expect(treeStub(wrapper).props('expandedIds')).toEqual(['catalog']);

        await wrapper.find('.tree-toggle').trigger('click');
        expect(treeStub(wrapper).props('expandedIds')).toEqual([]);
    });

    it('expands every entry at once and collapses them again', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.sw-system-menu-list__button-expand').trigger('click');
        expect(treeStub(wrapper).props('expandedIds')).toEqual(['catalog']);

        await wrapper.find('.sw-system-menu-list__button-expand').trigger('click');
        expect(treeStub(wrapper).props('expandedIds')).toEqual([]);
    });

    it('opens the parents of the selected entry', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.tree-select').trigger('click');

        expect(treeStub(wrapper).props('expandedIds')).toEqual(['catalog']);
    });

    it('filters the tree by label and route id while searching', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.search-bar-input').setValue('category');

        const items = treeStub(wrapper).props('items');

        expect(items).toHaveLength(1);
        expect(items[0].children.map((node) => node.id)).toEqual(['categories']);
    });

    it('expands every branch of the filtered tree while searching', async () => {
        wrapper = await createWrapper();

        expect(treeStub(wrapper).props('expandedIds')).toEqual([]);

        await wrapper.find('.search-bar-input').setValue('category');

        expect(treeStub(wrapper).props('expandedIds')).toEqual(['catalog']);

        await wrapper.find('.search-bar-input').setValue('');

        expect(treeStub(wrapper).props('expandedIds')).toEqual([]);
    });
});
