/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type UnitRow = {
    id: string;
    parentId: string | null;
    organizationId: string;
    name: string;
    active: boolean;
    translated: { name: string };
};

let saveSpy: jest.Mock;
let deleteSpy: jest.Mock;

const units: UnitRow[] = [
    {
        id: 'unit-root',
        parentId: null,
        organizationId: 'org-id',
        name: 'Headquarters',
        active: true,
        translated: { name: 'Headquarters' },
    },
    {
        id: 'unit-child',
        parentId: 'unit-root',
        organizationId: 'org-id',
        name: 'Engineering',
        active: true,
        translated: { name: 'Engineering' },
    },
    {
        id: 'unit-leaf',
        parentId: 'unit-child',
        organizationId: 'org-id',
        name: 'Frontend',
        active: true,
        translated: { name: 'Frontend' },
    },
];

async function createWrapper(privileges: string[] = []): Promise<VueWrapper> {
    saveSpy = jest.fn(() => Promise.resolve());
    deleteSpy = jest.fn(() => Promise.resolve());
    const rows = units.map((unit) => ({ ...unit }));
    return shallowMount(
        await wrapTestComponent('sw-settings-organization-detail', {
            sync: true,
        }),
        {
            props: {
                organizationId: 'org-id',
            },
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'regular-officebuilding',
                            },
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () =>
                                Promise.resolve({
                                    id: 'org-id',
                                    name: 'Shopwell HQ',
                                    active: true,
                                }),
                            search: () => Promise.resolve(rows),
                            save: saveSpy,
                            delete: deleteSpy,
                            create: () => ({
                                organizationId: null,
                                parentId: null,
                                name: null,
                                active: true,
                            }),
                        }),
                    },
                    acl: {
                        can: (key: string) => (key ? privileges.includes(key) : true),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-header"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                    </div>
                `,
                    },
                    'sw-card-view': { template: '<div><slot /></div>' },
                    'mt-card': { template: '<section><slot name="grid"/><slot /></section>' },
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                    'mt-text-field': {
                        props: ['modelValue', 'disabled'],
                        emits: ['update:modelValue'],
                        template: `<input :disabled="disabled" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
                    },
                    'mt-number-field': {
                        props: ['modelValue', 'disabled'],
                        emits: ['update:modelValue'],
                        template: `<input type="number" :disabled="disabled" :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))" />`,
                    },
                    'mt-switch': {
                        props: ['modelValue', 'disabled'],
                        emits: ['update:modelValue'],
                        template: `<input type="checkbox" :disabled="disabled" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
                    },
                    'sw-container': {
                        template: `
                            <div class="sw-container">
                                <slot></slot>
                            </div>`,
                    },
                    'sw-ignore-class': true,
                    'sw-settings-organization-unit-tree': {
                        props: ['units', 'selectedUnitId'],
                        emits: ['select-unit', 'add-child'],
                        template: `
                            <div class="sw-settings-organization-unit-tree">
                                <template
                                    v-for="unit in units"
                                    :key="unit.id"
                                >
                                    <button
                                        class="tree-unit"
                                        @click="$emit('select-unit', unit)"
                                    >{{ unit.name }}</button>
                                    <button
                                        class="tree-add-child"
                                        @click="$emit('add-child', unit)"
                                    >+</button>
                                </template>
                            </div>`,
                    },
                    'sw-search-bar': true,
                    'mt-select': {
                        props: ['modelValue', 'options', 'disabled'],
                        emits: ['update:model-value'],
                        template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:model-value', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                    },
                    'sw-context-menu-item': true,
                    'sw-loader': true,
                    'sw-extension-component-section': true,
                    'sw-skeleton': true,
                    'sw-modal': {
                        template: `
                            <div class="sw-modal">
                                <slot></slot>
                                <slot name="modal-footer"></slot>
                            </div>`,
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-organization/page/sw-settings-organization-detail', () => {
    let wrapper: VueWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should load the organization and its units', () => {
        expect(wrapper.get('h2').text()).toContain('Shopwell HQ');
        expect(wrapper.findAll('.tree-unit')).toHaveLength(3);
    });

    it('should show the empty state until a unit is selected', () => {
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
    });

    it('should load the unit form when a unit is selected', async () => {
        await wrapper.findAll('.tree-unit')[0].trigger('click');

        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Headquarters',
        );
    });

    it('should exclude the unit and its descendants from the parent options (circular parent protection)', async () => {
        await wrapper.findAll('.tree-unit')[1].trigger('click');

        const optionIds = wrapper
            .findAll('.sw-settings-organization-detail__unit-parent option')
            .map((option) => option.attributes('value'));

        expect(optionIds).toContain('unit-root');
        expect(optionIds).not.toContain('unit-child');
        expect(optionIds).not.toContain('unit-leaf');
    });

    it('prevents choosing a descendant parent before saving', async () => {
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[1].trigger('click');

        expect(wrapper.find('.sw-settings-organization-detail__unit-parent option[value="unit-leaf"]').exists()).toBe(false);
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe(
            'unit-root',
        );
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('should save unit edits immediately', async () => {
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[0].trigger('click');

        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('HQ renamed');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'HQ renamed',
            }),
        );
    });

    it('should create a child unit below the hovered parent', async () => {
        wrapper = await createWrapper(['organization.creator', 'organization.editor']);
        await flushPromises();

        await wrapper.findAll('.tree-add-child')[0].trigger('click');
        expect(wrapper.find('.sw-settings-organization-detail__create-unit-modal').exists()).toBe(true);
        await wrapper.get('.sw-settings-organization-detail__create-unit-name').setValue('Support');

        await wrapper.findAll('.sw-settings-organization-detail__create-unit-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                organizationId: 'org-id',
                parentId: 'unit-root',
                name: 'Support',
            }),
        );
        expect(wrapper.find('.sw-settings-organization-detail__create-unit-modal').exists()).toBe(false);
    });

    it('should confirm the delete of the selected unit', async () => {
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[2].trigger('click');

        await wrapper.get('.sw-settings-organization-detail__delete-unit').trigger('click');
        expect(wrapper.find('.sw-settings-organization-detail__delete-unit-modal').exists()).toBe(true);
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-settings-organization-detail__delete-unit-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('unit-leaf');
    });
});
