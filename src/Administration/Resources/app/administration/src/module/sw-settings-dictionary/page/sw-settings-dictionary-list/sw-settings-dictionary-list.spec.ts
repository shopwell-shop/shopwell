/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type DictionaryTypeVm = {
    dictionaryTypes: Array<{
        id: string;
        technicalName: string;
        active: boolean;
        entries?: Array<{ id: string; technicalName: string }>;
    }>;
    newType: {
        label: string | null;
        technicalName: string | null;
        active: boolean;
    };
    showCreateModal: boolean;
    showDeleteModal: string | null;
    dictionaryTypeRepository: {
        save: jest.Mock;
        delete: jest.Mock;
    };
    getEntryCount: (item: { entries?: unknown[] }) => number;
    onAddType: () => void;
    onCreateSave: () => Promise<void>;
    onDelete: (id: string) => void;
    onConfirmDelete: (id: string) => Promise<void>;
};

type TypedWrapper = { vm: DictionaryTypeVm } & VueWrapper;

async function createWrapper(privileges: string[] = []): Promise<TypedWrapper> {
    return mount(
        await wrapTestComponent('sw-settings-dictionary-list', {
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
                                icon: 'regular-layer-group',
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
                                        id: 'type-id',
                                        technicalName: 'salutation',
                                        translated: { label: 'Salutation' },
                                        active: true,
                                        entries: [
                                            { id: 'entry-1', technicalName: 'mr' },
                                            { id: 'entry-2', technicalName: 'mrs' },
                                        ],
                                    },
                                ]),
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
                            create: () => ({
                                technicalName: null,
                                label: null,
                                active: true,
                            }),
                        }),
                    },
                    acl: {
                        can: (key: string) => (key ? privileges.includes(key) : true),
                    },
                    dictionaryService: {
                        invalidateCaches: () => {},
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

describe('module/sw-settings-dictionary/page/sw-settings-dictionary-list', () => {
    let wrapper: TypedWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should not allow creating a type without permission', () => {
        const addButton = wrapper.find('.sw-settings-dictionary-list__add-type');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should allow creating a type with creator permission', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-dictionary-list__add-type');

        expect(addButton.attributes('disabled')).toBeUndefined();
    });

    it('should not allow deleting without permission', () => {
        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-delete');

        expect(deleteMenuItem.attributes('disabled')).toBeDefined();
    });

    it('should allow deleting with deleter permission', async () => {
        wrapper = await createWrapper(['dictionary.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-delete');

        expect(deleteMenuItem.attributes('disabled')).toBeUndefined();
    });

    it('should load the dictionary types and show the entry count', () => {
        const entriesColumn = wrapper.find('.sw-settings-dictionary-list-grid');

        expect(entriesColumn.exists()).toBe(true);
        expect(wrapper.vm.dictionaryTypes).toHaveLength(1);
        expect(wrapper.vm.getEntryCount(wrapper.vm.dictionaryTypes[0])).toBe(2);
    });

    it('should create a new dictionary type with technical name and active flag', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        wrapper.vm.onAddType();

        expect(wrapper.vm.newType).not.toBeNull();

        wrapper.vm.newType.label = 'Salutation';
        wrapper.vm.newType.technicalName = 'salutation';
        wrapper.vm.newType.active = true;

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.dictionaryTypeRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                technicalName: 'salutation',
                active: true,
            }),
        );
        expect(wrapper.vm.showCreateModal).toBe(false);
    });

    it('should reject a new type without technical name', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        wrapper.vm.onAddType();
        wrapper.vm.newType.label = 'Salutation';

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.dictionaryTypeRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.vm.showCreateModal).toBe(true);
    });

    it('should confirm the delete of a type', async () => {
        wrapper = await createWrapper(['dictionary.deleter']);
        await flushPromises();

        const deleteSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.dictionaryTypeRepository.delete = deleteSpy;

        wrapper.vm.onDelete('type-id');
        expect(wrapper.vm.showDeleteModal).toBe('type-id');

        await wrapper.vm.onConfirmDelete('type-id');

        expect(deleteSpy).toHaveBeenCalledWith('type-id');
        expect(wrapper.vm.showDeleteModal).toBeNull();
    });
});
