/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { categories, createWrapper } from './sw-blog-category-navigation.spec/create-wrapper';

let wrapper: VueWrapper;

describe('Blog category tree navigation', () => {
    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
    });

    it('expands the selected path and lets merchants collapse and reopen branches', async () => {
        ({ wrapper } = await createWrapper());
        expect(wrapper.get('[data-item-id="washing"]').attributes('aria-current')).toBe('page');
        await wrapper
            .get('[data-item-id="guides"] > .sw-blog-category-tree__row .sw-blog-category-tree__toggle')
            .trigger('click');
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(false);
        await wrapper
            .get('[data-item-id="guides"] > .sw-blog-category-tree__row .sw-blog-category-tree__toggle')
            .trigger('click');
        expect(wrapper.find('[data-item-id="care"]').exists()).toBe(true);
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
        await wrapper
            .get('[data-item-id="care"] > .sw-blog-category-tree__row .sw-blog-category-tree__toggle')
            .trigger('click');
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(false);
    });

    it('searches matching categories with their ancestor paths and disables dragging', async () => {
        ({ wrapper } = await createWrapper());
        await wrapper.setProps({ searchTerm: 'Washing' });
        await flushPromises();
        expect(wrapper.find('[data-item-id="guides"]').exists()).toBe(true);
        expect(wrapper.find('[data-item-id="care"]').exists()).toBe(true);
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
        expect(wrapper.find('[data-item-id="news"]').exists()).toBe(false);
        expect(wrapper.get('.sw-blog-category-navigation__tree').attributes('data-sortable')).toBe('false');
        await wrapper.setProps({ searchTerm: 'Guides' });
        await flushPromises();
        expect(wrapper.find('[data-item-id="guides"] .sw-blog-category-tree__toggle').exists()).toBe(false);
    });

    it('shows the selected hierarchy and provides explicit expand, collapse, and clear-search controls', async () => {
        ({ wrapper } = await createWrapper());
        expect(wrapper.emitted('selection-path')?.at(-1)).toEqual(['Guides / Product care / Washing']);
        await wrapper.get('.sw-blog-category-navigation__collapse-all').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-item-id="care"]').exists()).toBe(false);
        await wrapper.get('.sw-blog-category-navigation__expand-all').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
        await wrapper.setProps({ searchTerm: 'Washing' });
        await wrapper.setProps({ searchTerm: '' });
        await flushPromises();
        expect(wrapper.find('[data-item-id="news"]').exists()).toBe(true);
    });

    it('requests a sibling draft without persisting a category', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        await wrapper
            .get('[data-item-id="care"] > .sw-blog-category-tree__row .sw-blog-category-navigation__add-sibling')
            .trigger('click');
        expect(wrapper.emitted('create')).toEqual([[{ parentId: 'guides', afterCategoryId: 'care' }]]);
        expect(result.save).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-blog-category-navigation__create-name').exists()).toBe(false);
    });

    it('keeps child creation in the node menu without a duplicate quick-add button', async () => {
        ({ wrapper } = await createWrapper());
        expect(wrapper.find('.sw-blog-category-navigation__quick-add').exists()).toBe(false);
        await wrapper
            .get('[data-item-id="care"] > .sw-blog-category-tree__row .sw-blog-category-navigation__add-child')
            .trigger('click');
        expect(wrapper.emitted('create')).toEqual([[{ parentId: 'care', afterCategoryId: 'washing' }]]);
        expect(wrapper.find('.sw-blog-category-navigation__create-name').exists()).toBe(false);
    });

    it('restores expanded branches after changing and clearing a search with no matches', async () => {
        ({ wrapper } = await createWrapper({ selectedId: 'news' }));
        await wrapper.get('.sw-blog-category-navigation__expand-all').trigger('click');
        await flushPromises();
        await wrapper.setProps({ searchTerm: 'Washing' });
        await flushPromises();
        await wrapper.setProps({ searchTerm: 'Missing category' });
        await flushPromises();
        await wrapper.setProps({ searchTerm: '' });
        await flushPromises();
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
    });

    it('loads all category pages rather than dropping nodes after a fixed limit', async () => {
        const allCategories = Array.from({ length: 205 }, (_, index) => ({
            ...categories[1],
            id: `category-${index}`,
            name: `Category ${index}`,
            afterCategoryId: index ? `category-${index - 1}` : null,
        }));
        const result = await createWrapper({ allCategories, selectedId: 'category-204' });
        wrapper = result.wrapper;
        expect(result.search).toHaveBeenCalledTimes(3);
        expect(wrapper.find('[data-item-id="category-204"]').exists()).toBe(true);
    });

    it('requests a child draft and lets the workspace guard the editor', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        await wrapper
            .get('[data-item-id="care"] > .sw-blog-category-tree__row .sw-blog-category-navigation__add-child')
            .trigger('click');
        expect(wrapper.emitted('create')).toEqual([[{ parentId: 'care', afterCategoryId: 'washing' }]]);
        expect(result.save).not.toHaveBeenCalled();
    });

    it('does not move when the unsaved-change gate is canceled', async () => {
        const result = await createWrapper({ beforeStructureChange: () => Promise.resolve(false) });
        wrapper = result.wrapper;
        await wrapper.get('[data-item-id="news"] .sw-blog-category-navigation__move-up').trigger('click');
        await flushPromises();
        expect(result.moveCategory).not.toHaveBeenCalled();
    });

    it('moves siblings up and down and moves a child to root through keyboard-accessible menus', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        await wrapper.get('[data-item-id="news"] .sw-blog-category-navigation__move-up').trigger('click');
        await flushPromises();
        expect(result.moveCategory).toHaveBeenLastCalledWith('news', null, null);
        await wrapper
            .get('[data-item-id="guides"] > .sw-blog-category-tree__row .sw-blog-category-navigation__move-down')
            .trigger('click');
        await flushPromises();
        expect(result.moveCategory).toHaveBeenLastCalledWith('guides', null, 'news');
        await wrapper.get('[data-item-id="washing"] .sw-blog-category-navigation__move-root').trigger('click');
        await flushPromises();
        expect(result.moveCategory).toHaveBeenLastCalledWith('washing', null, 'news');
    });

    it('preserves the tree after a move failure and keeps viewers read-only', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        result.moveCategory.mockRejectedValueOnce(new Error('move rejected'));
        await wrapper.get('[data-item-id="news"] .sw-blog-category-navigation__move-up').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('[aria-level="1"]').map((item) => item.attributes('data-item-id'))).toEqual([
            'guides',
            'news',
        ]);
        wrapper.unmount();
        ({ wrapper } = await createWrapper({ canEdit: false }));
        expect(wrapper.find('.sw-blog-category-navigation__move').exists()).toBe(false);
        expect(wrapper.find('.sw-blog-category-navigation__quick-add').exists()).toBe(false);
        expect(wrapper.get('.sw-blog-category-navigation__tree').attributes('data-sortable')).toBe('false');
    });

    it('locks the workspace only after accepting a move and unlocks after persistence', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        let finishMove: (() => void) | undefined;
        result.moveCategory.mockReturnValueOnce(
            new Promise<void>((resolve) => {
                finishMove = resolve;
            }),
        );
        await wrapper.get('[data-item-id="news"] .sw-blog-category-navigation__move-up').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('structure-busy')).toEqual([[true]]);
        finishMove?.();
        await flushPromises();
        expect(wrapper.emitted('structure-busy')).toEqual([[true], [false]]);
    });

    it('blocks dragging a parent into its descendant and restores the original tree', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        const target = wrapper.get('[data-item-id="care"] > .sw-blog-category-tree__row');
        const source = wrapper.get('[data-item-id="guides"] > .sw-blog-category-tree__row');
        await source.get('.sw-blog-category-navigation__drag-handle').trigger('dragstart');
        await target.trigger('dragover');
        await flushPromises();
        expect(wrapper.get('.sw-blog-category-navigation__drop-hint').text()).toContain('sw-blog.category.dropInvalid');
        await target.trigger('drop');
        await flushPromises();
        expect(result.moveCategory).not.toHaveBeenCalled();
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
    });
});
