/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type PositionVm = {
    positions: Array<{ id: string; name: string; position: number; active: boolean }>;
    isNewPosition: boolean;
    showEditModal: boolean;
    showDeleteModal: string | null;
    editedPosition: { id: string; name: string | null; position: number | null; active: boolean };
    positionRepository: {
        save: jest.Mock;
        delete: jest.Mock;
    };
    onAddPosition: () => void;
    onEditModalSave: () => Promise<void>;
    onDelete: (id: string) => void;
    onConfirmDelete: (id: string) => Promise<void>;
};

type TypedWrapper = { vm: PositionVm } & VueWrapper;

async function createWrapper(privileges: string[] = []): Promise<TypedWrapper> {
    return mount(
        await wrapTestComponent('sw-settings-position-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        meta: {
                            $module: {
                                icon: 'regular-briefcase',
                            },
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () =>
                                Promise.resolve([
                                    {
                                        id: 'position-1',
                                        name: 'Manager',
                                        position: 10,
                                        active: true,
                                        translated: { name: 'Manager' },
                                    },
                                ]),
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
                            create: () => ({
                                id: 'new-position-id',
                                name: null,
                                position: null,
                                active: true,
                            }),
                        }),
                    },
                    acl: {
                        can: (key: string) => (key ? privileges.includes(key) : true),
                    },
                    searchRankingService: {
                        isValidTerm: (term: string) => {
                            return term && term.trim().length >= 1;
                        },
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
                    'sw-ignore-class': true,
                    'sw-entity-listing': {
                        props: ['items', 'dataSource'],
                        template: `
                    <div>
                        <template v-for="item in (dataSource || items)" :key="item.id">
                            <slot name="actions" v-bind="{ item }"></slot>
                            <slot name="action-modals" v-bind="{ item }"></slot>
                        </template>
                    </div>`,
                    },
                    'sw-language-switch': true,
                    'sw-search-bar': true,
                    'sw-context-menu-item': true,
                    'sw-loader': true,
                    'sw-extension-component-section': true,
                    'sw-label': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
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

describe('module/sw-settings-position/page/sw-settings-position-list', () => {
    let wrapper: TypedWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should not allow adding a position without permission', () => {
        const addButton = wrapper.find('.sw-settings-position-list__add-position');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should allow adding a position with creator permission', async () => {
        wrapper = await createWrapper(['position.creator']);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-position-list__add-position');

        expect(addButton.attributes('disabled')).toBeUndefined();
    });

    it('should load positions', () => {
        expect(wrapper.vm.positions).toHaveLength(1);
        expect(wrapper.vm.positions[0].name).toBe('Manager');
    });

    it('should save a new position with sort order and active flag via the modal', async () => {
        wrapper = await createWrapper(['position.creator']);
        await flushPromises();

        wrapper.vm.onAddPosition();

        expect(wrapper.vm.isNewPosition).toBe(true);

        wrapper.vm.editedPosition.name = 'Manager';
        wrapper.vm.editedPosition.position = 10;
        wrapper.vm.editedPosition.active = true;

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.positionRepository.save = saveSpy;

        await wrapper.vm.onEditModalSave();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Manager',
                position: 10,
                active: true,
            }),
        );
        expect(wrapper.vm.showEditModal).toBe(false);
    });

    it('should reject a position without a name', async () => {
        wrapper = await createWrapper(['position.creator']);
        await flushPromises();

        wrapper.vm.onAddPosition();

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.positionRepository.save = saveSpy;

        await wrapper.vm.onEditModalSave();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.vm.showEditModal).toBe(true);
    });

    it('should not allow deleting without permission', () => {
        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-delete');

        expect(deleteMenuItem.attributes('disabled')).toBeDefined();
    });

    it('should allow deleting with deleter permission and confirm the delete', async () => {
        wrapper = await createWrapper(['position.deleter']);
        await flushPromises();

        const deleteSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.positionRepository.delete = deleteSpy;

        wrapper.vm.onDelete('position-1');
        expect(wrapper.vm.showDeleteModal).toBe('position-1');

        await wrapper.vm.onConfirmDelete('position-1');

        expect(deleteSpy).toHaveBeenCalledWith('position-1');
    });
});
