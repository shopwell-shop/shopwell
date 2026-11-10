/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { defineAsyncComponent } from 'vue';

type Row = { id: string; name: string; active: boolean; isNew?: () => boolean };
const organizations = [
    { id: 'org-a', name: 'Acme Europe', active: true },
    { id: 'org-b', name: 'Acme Asia', active: true },
];
const units = [
    { id: 'root', organizationId: 'org-a', parentId: null, name: 'Headquarters', active: true },
    { id: 'child', organizationId: 'org-a', parentId: 'root', name: 'Engineering', active: true },
    { id: 'asia', organizationId: 'org-b', parentId: null, name: 'Operations', active: true },
];

describe('sw-settings-organization-unit-tree', () => {
    let wrapper: VueWrapper;
    let finishTreeLoading: (() => void) | undefined;
    afterEach(() => wrapper?.unmount());

    async function render(
        props: Record<string, unknown> = {},
        privileges = ['organization.creator', 'organization.deleter'],
        deferTree = false,
    ) {
        const tree = await wrapTestComponent('sw-tree', { sync: true });
        wrapper = mount(await wrapTestComponent('sw-settings-organization-unit-tree', { sync: true }), {
            props: { organizations, units, selectedOrganizationId: 'org-a', selectedUnitId: 'child', ...props },
            global: {
                provide: { acl: { can: (key: string) => privileges.includes(key) } },
                stubs: {
                    'sw-vnode-renderer': await wrapTestComponent('sw-vnode-renderer', { sync: true }),
                    'sw-tree': deferTree
                        ? defineAsyncComponent(
                              () =>
                                  new Promise((resolve) => {
                                      finishTreeLoading = () => resolve(tree);
                                  }),
                          )
                        : tree,
                    'sw-tree-item': await wrapTestComponent('sw-tree-item', { sync: true }),
                    'sw-context-button': { template: '<div class="node-menu"><slot /></div>' },
                    'sw-context-menu-item': {
                        props: ['disabled'],
                        template: '<button :disabled="disabled"><slot /></button>',
                    },
                },
            },
        });
        await flushPromises();
    }

    it('shows multiple institutions as independent roots and their units below them', async () => {
        await render();
        const roots = wrapper.findAll('.tree-items > .sw-tree-item');
        expect(roots).toHaveLength(2);
        expect(roots[0].text()).toContain('Acme Europe');
        expect(roots[0].text()).toContain('Headquarters');
        expect(roots[0].text()).toContain('Engineering');
        expect(roots[0].text()).not.toContain('Operations');
        expect(roots[1].text()).toContain('Acme Asia');
    });

    it('selects institutions and units using ordinary tree links', async () => {
        await render();
        await wrapper.get('[data-item-id="organization:org-b"] .tree-link').trigger('click');
        expect(wrapper.emitted('select-organization')).toEqual([[organizations[1]]]);
        await wrapper.get('[data-item-id="unit:child"] .tree-link').trigger('click');
        expect(wrapper.emitted('select-unit')).toEqual([[units[1]]]);
    });

    it('opens the selected branch after initial navigation loading finishes', async () => {
        await render({ isLoading: true });
        await wrapper.setProps({ isLoading: false });
        await flushPromises();
        expect(wrapper.get('[data-item-id="organization:org-a"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[data-item-id="unit:root"]').attributes('aria-expanded')).toBe('true');
    });

    it('opens selection when the shared tree component finishes lazy loading', async () => {
        await render({}, [], true);
        finishTreeLoading?.();
        await flushPromises();
        expect(wrapper.get('[data-item-id="organization:org-a"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[data-item-id="unit:root"]').attributes('aria-expanded')).toBe('true');
    });

    it('offers context actions for the right entity type at every depth', async () => {
        await render();
        const organization = wrapper.get('[data-item-id="organization:org-a"]');
        await organization.get('.sw-settings-organization-unit-tree__add-unit').trigger('click');
        expect(wrapper.emitted('add-root')).toEqual([[organizations[0]]]);
        const child = wrapper.get('[data-item-id="unit:child"]');
        await child.get('.sw-settings-organization-unit-tree__add-child').trigger('click');
        await child.get('.sw-settings-organization-unit-tree__delete').trigger('click');
        expect(wrapper.emitted('add-child')).toEqual([[units[1]]]);
        expect(wrapper.emitted('delete-unit')).toEqual([[units[1]]]);
    });

    it('keeps the institution and all ancestors when searching across institutions', async () => {
        await render();
        await wrapper.setProps({ searchTerm: 'Engineering' });
        await flushPromises();
        expect(wrapper.text()).toContain('Acme Europe');
        expect(wrapper.text()).toContain('Headquarters');
        expect(wrapper.text()).toContain('Engineering');
        expect(wrapper.text()).not.toContain('Acme Asia');
        expect(wrapper.find('input').exists()).toBe(false);
    });

    it('does not show child loading for descendants excluded by the top search', async () => {
        await render({
            units: [...units, { ...units[1], id: 'grandchild', parentId: 'child', name: 'Frontend' }],
            searchTerm: 'Engineering',
        });
        const result = wrapper.get('[data-item-id="unit:child"]');
        expect(result.text()).toContain('Engineering');
        expect(result.text()).not.toContain('Frontend');
        expect(result.find('.sw-skeleton').exists()).toBe(false);
        expect(result.classes()).toContain('is--no-children');
    });

    it('does not attach a unit to a parent from another institution', async () => {
        await render({
            units: [...units, { ...units[2], id: 'bad-parent', parentId: 'root' }],
            selectedUnitId: 'bad-parent',
            selectedOrganizationId: 'org-b',
        });
        const europe = wrapper.get('[data-item-id="organization:org-a"]');
        const asia = wrapper.get('[data-item-id="organization:org-b"]');
        expect(europe.text()).not.toContain('Operations');
        expect(asia.text()).toContain('Operations');
    });

    it('labels drafts and prevents creating children or deleting before Save', async () => {
        const draft: Row = { id: 'draft', name: '', active: true, isNew: () => true };
        await render({ units: [{ ...draft, organizationId: 'org-a', parentId: null }], selectedUnitId: 'draft' });
        const node = wrapper.get('[data-item-id="unit:draft"]');
        expect(node.get('.tree-link').text()).not.toBe('');
        expect(node.get('.sw-settings-organization-unit-tree__add-child').attributes('disabled')).toBeDefined();
        expect(node.get('.sw-settings-organization-unit-tree__delete').attributes('disabled')).toBeDefined();
    });

    it('does not start child loading for a selected leaf or a new institution', async () => {
        await render();
        expect(wrapper.find('[data-item-id="unit:child"] .sw-skeleton').exists()).toBe(false);
        wrapper.unmount();
        await render({ organizations: [{ ...organizations[0], isNew: () => true }], units: [], selectedUnitId: null });
        expect(wrapper.find('.sw-skeleton').exists()).toBe(false);
        expect(wrapper.emitted('load-organization')).toBeUndefined();
    });

    it('hides write actions for viewers', async () => {
        await render({}, []);
        expect(wrapper.findAll('.node-menu').map((node) => node.html())).toEqual([]);
    });

    it('renders an empty state without a naming input', async () => {
        await render({ organizations: [], units: [] });
        expect(wrapper.find('.sw-tree-input-field').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-organization-unit-tree__empty').exists()).toBe(true);
    });
});
