/** @sw-package framework */
import { mount } from '@vue/test-utils';
import treeSelect from './index';
import MtSearch from '@shopwell-ag/meteor-component-library/dist/esm/MtSearch';

describe('menu refinements', () => {
    it('reveals the selected path, navigates by keyboard and prevents selecting module branches', async () => {
        const wrapper = mount(treeSelect, {
            props: {
                modelValue: 'page',
                label: 'Page',
                options: [
                    { value: 'module', label: 'Module', selectable: false, children: [{ value: 'page', label: 'Page' }] },
                ],
            },
            global: {
                stubs: {
                    'mt-floating-ui': {
                        props: ['isOpened'],
                        template: '<div><slot name="trigger" /><slot v-if="isOpened" /></div>',
                    },
                    'mt-button': { template: '<button><slot /></button>' },
                    'mt-icon': true,
                    'mt-search': { props: ['size'], template: '<input />' },
                },
            },
        });
        await wrapper.get('.sw-tree-select__trigger').trigger('click');
        expect(wrapper.get('.sw-tree-select__value').text()).toBe('Module / Page');
        expect(wrapper.findAll('[role="treeitem"]')).toHaveLength(2);
        await wrapper.get('input').trigger('keydown', { key: 'ArrowUp' });
        await wrapper.get('[role="tree"]').trigger('keydown', { key: 'ArrowUp' });
        await wrapper.get('[role="tree"]').trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('update:model-value')).toBeUndefined();
        await wrapper.get('[role="tree"]').trigger('keydown', { key: 'ArrowRight' });
        await wrapper.get('[role="tree"]').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.get('[role="tree"]').trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('update:model-value')).toEqual([['page']]);
        wrapper.unmount();
    });
    it('renders the real Meteor popup and moves keyboard focus from search to the tree', async () => {
        const wrapper = mount(await wrapTestComponent('sw-tree-select', { sync: true }), {
            attachTo: document.body,
            global: { components: { 'mt-search': MtSearch } },
            props: { label: 'Parent', options: [{ value: 'folder', label: 'Folder' }] },
        });
        await wrapper.get('[role="combobox"]').trigger('click');
        await flushPromises();
        const popup = document.querySelector('.sw-tree-select__popup');
        expect(popup).not.toBeNull();
        const input = popup!.querySelector('input')!;
        input.focus();
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        await flushPromises();
        const tree = popup!.querySelector('[role="tree"]');
        expect(document.activeElement).toBe(tree);
        expect(tree!.getAttribute('aria-activedescendant')).toBe(popup!.querySelector('[role="treeitem"]')!.id);
        wrapper.unmount();
    });
});
