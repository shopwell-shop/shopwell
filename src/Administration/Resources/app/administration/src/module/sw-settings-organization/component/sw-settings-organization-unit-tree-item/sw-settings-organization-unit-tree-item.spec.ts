/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const unit = { id: 'root', name: 'Headquarters', translated: { name: 'Headquarters' }, active: true };

describe('sw-settings-organization-unit-tree-item', () => {
    let wrapper: VueWrapper;

    afterEach(() => wrapper?.unmount());

    async function render(canCreate = false) {
        wrapper = shallowMount(await wrapTestComponent('sw-settings-organization-unit-tree-item', { sync: true }), {
            props: { unit, unitTree: { root: unit }, filteredUnits: [unit], expandedIds: {} },
            global: { provide: { acl: { can: () => canCreate } } },
        });
    }

    it('lets keyboard users select a named unit', async () => {
        await render();

        expect(wrapper.get('[role="treeitem"]').attributes('aria-label')).toBe('Headquarters');
        await wrapper.get('.sw-settings-organization-unit-tree-item__row').trigger('keydown.enter');

        expect(wrapper.emitted('select-unit')).toEqual([[unit]]);
    });

    it('adds a child without also selecting the parent', async () => {
        await render(true);
        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('add-child')).toEqual([[unit]]);
        expect(wrapper.emitted('select-unit')).toBeUndefined();
    });

    it('hides creation controls from readers', async () => {
        await render();

        expect(wrapper.find('button').exists()).toBe(false);
    });
});
