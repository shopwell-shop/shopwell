/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createWrapper } from './sw-blog-category-navigation.spec/create-wrapper';

let wrapper: VueWrapper;

async function drag(sourceId: string, targetId: string, clientY = 0) {
    await wrapper.get(`[data-item-id="${sourceId}"] .sw-blog-category-navigation__drag-handle`).trigger('dragstart');
    await wrapper.get(`[data-item-id="${targetId}"] > .sw-blog-category-tree__row`).trigger('dragover', { clientY });
}

async function drop(targetId: string, clientY = 0) {
    await wrapper.get(`[data-item-id="${targetId}"] > .sw-blog-category-tree__row`).trigger('drop', { clientY });
    await flushPromises();
}

describe('Blog category tree following the system menu tree', () => {
    afterEach(() => wrapper?.unmount());

    it.each([
        ['before', 1, null],
        ['inside', 18, 'guides'],
    ])('previews a %s drop from the pointer position without moving rows', async (zone, clientY, parentId) => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        jest.spyOn(wrapper.get('[data-item-id="guides"]').element, 'getBoundingClientRect').mockReturnValue({
            top: 0,
            height: 36,
        } as DOMRect);
        await drag('news', 'guides', clientY);
        expect(wrapper.get(`[data-item-id="guides"] > .sw-blog-category-tree__row`).classes()).toContain(`is--drop-${zone}`);
        expect(result.moveCategory).not.toHaveBeenCalled();
        await drop('guides', clientY);
        expect(result.moveCategory).toHaveBeenCalledWith('news', parentId, zone === 'inside' ? 'care' : null);
    });

    it('does not persist dropping into the same sibling position', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        jest.spyOn(wrapper.get('[data-item-id="guides"]').element, 'getBoundingClientRect').mockReturnValue({
            top: 0,
            height: 36,
        } as DOMRect);
        await drag('news', 'guides', 35);
        await drop('guides', 35);
        expect(result.moveCategory).not.toHaveBeenCalled();
    });

    it('blocks dropping onto a descendant without sending a request', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        await drag('guides', 'washing');
        expect(wrapper.get('.sw-blog-category-navigation__drop-hint').text()).toContain('sw-blog.category.dropInvalid');
        await drop('washing');
        expect(result.moveCategory).not.toHaveBeenCalled();
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
    });

    it('resolves unsaved changes before persisting a drop', async () => {
        const result = await createWrapper({ beforeStructureChange: () => Promise.resolve(false) });
        wrapper = result.wrapper;
        await drag('news', 'guides');
        await drop('guides');
        expect(result.moveCategory).not.toHaveBeenCalled();
        expect(wrapper.find('[data-item-id="washing"]').exists()).toBe(true);
    });

    it('keeps the original order and selection after a rejected move', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        result.moveCategory.mockRejectedValueOnce(new Error('move rejected'));
        await drag('news', 'guides');
        await drop('guides');
        expect(wrapper.findAll('[aria-level="1"]').map((item) => item.attributes('data-item-id'))).toEqual([
            'guides',
            'news',
        ]);
        expect(wrapper.get('[data-item-id="washing"]').attributes('aria-current')).toBe('page');
    });

    it('supports keyboard selection and expanding or collapsing a branch', async () => {
        ({ wrapper } = await createWrapper());
        await wrapper.get('[data-item-id="guides"]').trigger('keydown', { key: 'ArrowLeft' });
        expect(wrapper.find('[data-item-id="care"]').exists()).toBe(false);
        await wrapper.get('[data-item-id="guides"]').trigger('keydown', { key: 'ArrowRight' });
        await wrapper.get('[data-item-id="guides"]').trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('select')?.at(-1)).toEqual(['guides']);
        expect(wrapper.find('[data-item-id="care"]').exists()).toBe(true);
    });
});
