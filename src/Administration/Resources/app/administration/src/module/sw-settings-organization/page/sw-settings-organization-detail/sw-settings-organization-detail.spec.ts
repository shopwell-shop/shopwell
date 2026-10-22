/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type UnitRow = {
    id: string;
    parentId: string | null;
    organizationId: string;
    name: string;
    active: boolean;
    translated: { name: string };
};

type OrganizationDetailVm = {
    organization: { name: string };
    units: UnitRow[];
    selectedUnit: {
        id: string;
        parentId: string | null;
        name: string;
    };
    parentOptions: Array<{ value: string; label: string }>;
    showCreateUnitModal: boolean;
    showDeleteModal: boolean;
    newUnitParent: { id: string };
    newUnitName: string;
    unitRepository: {
        create: jest.Mock;
        save: jest.Mock;
        delete: jest.Mock;
    };
    onSelectUnit: (unit: UnitRow) => void;
    onParentChange: (parentId: string) => Promise<void>;
    onUnitFieldChange: () => Promise<void>;
    onAddChild: (unit: UnitRow) => void;
    onCreateUnitSave: () => Promise<void>;
    onOpenDeleteModal: () => void;
    onConfirmDeleteUnit: () => Promise<void>;
};

type TypedWrapper = { vm: OrganizationDetailVm } & VueWrapper;

const units: UnitRow[] = [
    { id: 'unit-root', parentId: null, organizationId: 'org-id', name: 'Headquarters', active: true, translated: { name: 'Headquarters' } },
    { id: 'unit-child', parentId: 'unit-root', organizationId: 'org-id', name: 'Engineering', active: true, translated: { name: 'Engineering' } },
    { id: 'unit-leaf', parentId: 'unit-child', organizationId: 'org-id', name: 'Frontend', active: true, translated: { name: 'Frontend' } },
];

async function createWrapper(privileges: string[] = []): Promise<TypedWrapper> {
    return mount(
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
                            search: () => Promise.resolve(units),
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
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
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-card-view': true,
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
    ) as unknown as TypedWrapper;
}

describe('module/sw-settings-organization/page/sw-settings-organization-detail', () => {
    let wrapper: TypedWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should load the organization and its units', () => {
        expect(wrapper.vm.organization.name).toBe('Shopwell HQ');
        expect(wrapper.vm.units).toHaveLength(3);
    });

    it('should show the empty state until a unit is selected', () => {
        expect(wrapper.vm.selectedUnit).toBeNull();
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
    });

    it('should load the unit form when a unit is selected', () => {
        wrapper.vm.onSelectUnit(units[0]);

        expect(wrapper.vm.selectedUnit.id).toBe('unit-root');
    });

    it('should exclude the unit and its descendants from the parent options (circular parent protection)', () => {
        wrapper.vm.onSelectUnit(units[1]);

        const optionIds = wrapper.vm.parentOptions.map((option) => option.value);

        expect(optionIds).toContain('unit-root');
        expect(optionIds).not.toContain('unit-child');
        expect(optionIds).not.toContain('unit-leaf');
    });

    it('should reject saving a unit whose parent is one of its descendants', async () => {
        wrapper.vm.onSelectUnit(units[1]);

        await wrapper.vm.onParentChange('unit-leaf');

        // the change must not have been written
        expect(wrapper.vm.selectedUnit.parentId).toBe('unit-root');
    });

    it('should save unit edits immediately', async () => {
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        wrapper.vm.onSelectUnit(units[0]);

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.unitRepository.save = saveSpy;

        wrapper.vm.selectedUnit.name = 'HQ renamed';

        await wrapper.vm.onUnitFieldChange();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'HQ renamed',
            }),
        );
    });

    it('should create a child unit below the hovered parent', async () => {
        wrapper = await createWrapper(['organization.creator', 'organization.editor']);
        await flushPromises();

        wrapper.vm.onAddChild(units[0]);

        expect(wrapper.vm.showCreateUnitModal).toBe(true);
        expect(wrapper.vm.newUnitParent.id).toBe('unit-root');

        wrapper.vm.newUnitName = 'Support';

        const createSpy = jest.fn(() => ({
            organizationId: null,
            parentId: null,
            name: null,
            active: true,
        }));
        const saveSpy = jest.fn(() => Promise.resolve());

        wrapper.vm.unitRepository.create = createSpy;
        wrapper.vm.unitRepository.save = saveSpy;

        await wrapper.vm.onCreateUnitSave();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                organizationId: 'org-id',
                parentId: 'unit-root',
                name: 'Support',
            }),
        );
        expect(wrapper.vm.showCreateUnitModal).toBe(false);
    });

    it('should confirm the delete of the selected unit', async () => {
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();

        wrapper.vm.onSelectUnit(units[2]);

        const deleteSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.unitRepository.delete = deleteSpy;

        wrapper.vm.onOpenDeleteModal();
        expect(wrapper.vm.showDeleteModal).toBe(true);

        await wrapper.vm.onConfirmDeleteUnit();

        expect(deleteSpy).toHaveBeenCalledWith('unit-leaf');
    });
});
