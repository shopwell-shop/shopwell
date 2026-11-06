/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const root = { id: 'root', parentId: null, name: 'Headquarters', active: true };
const child = { id: 'child', parentId: 'root', name: 'Engineering', active: true };

describe('sw-settings-organization-unit-tree', () => {
    let wrapper: VueWrapper;

    afterEach(() => wrapper?.unmount());

    async function render(units: Array<{ id: string; parentId: string | null; name: string; active: boolean }>) {
        wrapper = shallowMount(await wrapTestComponent('sw-settings-organization-unit-tree', { sync: true }), {
            props: { units },
            global: {
                provide: { acl: { can: () => false } },
                stubs: {
                    'sw-settings-organization-unit-tree-item': {
                        props: ['unit'],
                        emits: ['select-unit'],
                        template: '<button @click="$emit(\'select-unit\', unit)">{{ unit.name }}</button>',
                    },
                },
            },
        });
    }

    it('renders roots and forwards selection to the detail page', async () => {
        await render([root]);
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').text()).toBe('Headquarters');
        expect(wrapper.emitted('select-unit')).toEqual([[root]]);
    });

    it('renders an orphan as a root so filtered results remain reachable', async () => {
        await render([child]);

        expect(wrapper.get('button').text()).toBe('Engineering');
    });

    it('shows the empty-state message without nodes', async () => {
        await render([]);

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.get('.sw-settings-organization-unit-tree__empty-text').text()).not.toBe('');
    });
});
