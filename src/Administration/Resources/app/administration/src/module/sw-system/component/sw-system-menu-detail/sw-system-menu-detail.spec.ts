/**
 * @sw-package fundamentals@framework
 */
import MtButton from '@shopwell-ag/meteor-component-library/dist/esm/MtButton';
import MtTextField from '@shopwell-ag/meteor-component-library/dist/esm/MtTextField';
import MtSelect from '@shopwell-ag/meteor-component-library/dist/esm/MtSelect';
import MtSwitch from '@shopwell-ag/meteor-component-library/dist/esm/MtSwitch';
import { mount } from '@vue/test-utils';
import type Repository from 'src/core/data/repository.data';
import { createDraft } from '../../page/sw-system-menu-list/menu-draft';

async function createWrapper(disabled = false, draft = createDraft({ title: 'Resources', type: 'folder' })) {
    return mount(await wrapTestComponent('sw-system-menu-detail', { sync: true }), {
        props: {
            draft,
            disabled,
            languageLabel: 'English',
            isGroupable: true,
            typeOptions: [{ value: 'folder', label: 'Folder' }],
            parentOptions: [],
            routeOptions: [],
            groupOptions: [],
            iconOptions: [],
            privilegeOptions: [],
            fieldErrors: {},
            aclRoleRepository: {} as Repository<'acl_role'>,
            roleCriteria: new Shopwell.Data.Criteria(),
        },
        global: {
            stubs: {
                'mt-text-field': MtTextField,
                'mt-button': MtButton,
                'mt-select': MtSelect,
                'mt-switch': MtSwitch,
                'mt-card': { template: '<section><slot /></section>' },
                'sw-entity-multi-id-select': true,
                'mt-icon': true,
                'sw-tree-select': true,
                'sw-icon-picker': true,
                'mt-url-field': true,
            },
        },
    });
}

describe('menu detail editor', () => {
    it('emits field edits without mutating the parent draft', async () => {
        const draft = createDraft({ title: 'Resources', type: 'folder' });
        const wrapper = await createWrapper(false, draft);
        await wrapper.get('.sw-system-menu-list__field-title input').setValue('Tools');
        expect(wrapper.emitted('update-draft')).toEqual([[{ title: 'Tools' }]]);
        expect(draft.title).toBe('Resources');
        expect(wrapper.find('.sw-system-menu-list__field-target').exists()).toBe(false);
        wrapper.unmount();
    });

    it('disables the inputs for a read-only draft', async () => {
        const wrapper = await createWrapper(true);
        expect(wrapper.get('.sw-system-menu-list__field-title input').attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('button').some((button) => button.text().includes('sw-system.menu.createGroup'))).toBe(false);
        wrapper.unmount();
    });
});

describe('menu refinements', () => {
    it('uses one card and true tree selectors while groups have no target or icon', async () => {
        const wrapper = await createWrapper(false, createDraft({ title: 'Operations', type: 'group' }));
        expect(wrapper.findAll('section')).toHaveLength(1);
        expect(wrapper.find('sw-tree-select-stub').exists()).toBe(true);
        expect(wrapper.find('sw-icon-picker-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-system-menu-list__field-target').exists()).toBe(false);
        wrapper.unmount();
    });
});
