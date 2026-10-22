/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type DictionaryEntryRow = {
    id: string;
    technicalName: string;
    position: number;
    active: boolean;
    translated: { label: string };
};

type DictionaryDetailVm = {
    dictionaryType: { technicalName: string };
    entries: DictionaryEntryRow[] | null;
    newEntry: {
        dictionaryTypeId: string | null;
        label: string | null;
        technicalName: string | null;
        position: number | null;
        active: boolean;
    };
    showCreateModal: boolean;
    showDeleteModal: string | null;
    entryRepository: {
        save: jest.Mock;
        delete: jest.Mock;
    };
    onAddEntry: () => void;
    onCreateSave: () => Promise<void>;
    onDelete: (id: string) => void;
    onConfirmDelete: (id: string) => Promise<void>;
};

type TypedWrapper = { vm: DictionaryDetailVm } & VueWrapper;

async function createWrapper(privileges: string[] = [], entries: DictionaryEntryRow[] = []): Promise<TypedWrapper> {
    return mount(
        await wrapTestComponent('sw-settings-dictionary-detail', {
            sync: true,
        }),
        {
            props: {
                dictionaryTypeId: 'type-id',
            },
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
                            get: () =>
                                Promise.resolve({
                                    id: 'type-id',
                                    technicalName: 'salutation',
                                    translated: { label: 'Salutation' },
                                }),
                            search: () => Promise.resolve(entries),
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
                            create: () => ({
                                dictionaryTypeId: null,
                                technicalName: null,
                                label: null,
                                position: null,
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

describe('module/sw-settings-dictionary/page/sw-settings-dictionary-detail', () => {
    let wrapper: TypedWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should not allow adding an entry without permission', () => {
        const addButton = wrapper.find('.sw-settings-dictionary-detail__add-entry');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should allow adding an entry with creator permission', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-dictionary-detail__add-entry');

        expect(addButton.attributes('disabled')).toBeUndefined();
    });

    it('should load the type and its entries sorted by position', () => {
        expect(wrapper.vm.dictionaryType.technicalName).toBe('salutation');
    });

    it('should save a new entry with the payload of the current language', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        wrapper.vm.onAddEntry();

        expect(wrapper.vm.newEntry.dictionaryTypeId).toBe('type-id');

        wrapper.vm.newEntry.label = '先生';
        wrapper.vm.newEntry.technicalName = 'mr';
        wrapper.vm.newEntry.position = 10;
        wrapper.vm.newEntry.active = true;

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.entryRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                dictionaryTypeId: 'type-id',
                technicalName: 'mr',
                position: 10,
                active: true,
                label: '先生',
            }),
        );
        expect(wrapper.vm.showCreateModal).toBe(false);
    });

    it('should reject an entry whose technical name duplicates an existing entry', async () => {
        wrapper = await createWrapper(
            ['dictionary.creator'],
            [
                {
                    id: 'entry-1',
                    technicalName: 'mr',
                    position: 10,
                    active: true,
                    translated: { label: 'Mr.' },
                },
            ],
        );
        await flushPromises();

        wrapper.vm.onAddEntry();

        wrapper.vm.newEntry.label = '先生';
        wrapper.vm.newEntry.technicalName = 'mr';

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.entryRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.vm.showCreateModal).toBe(true);
    });

    it('should pre-fill the position with the next free slot', async () => {
        wrapper = await createWrapper(
            ['dictionary.creator'],
            [
                {
                    id: 'entry-1',
                    technicalName: 'mr',
                    position: 10,
                    active: true,
                    translated: { label: 'Mr.' },
                },
            ],
        );
        await flushPromises();

        wrapper.vm.onAddEntry();

        expect(wrapper.vm.newEntry.position).toBe(20);
    });

    it('should confirm the delete of an entry', async () => {
        wrapper = await createWrapper(['dictionary.deleter']);
        await flushPromises();

        const deleteSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.entryRepository.delete = deleteSpy;

        wrapper.vm.onDelete('entry-1');
        expect(wrapper.vm.showDeleteModal).toBe('entry-1');

        await wrapper.vm.onConfirmDelete('entry-1');

        expect(deleteSpy).toHaveBeenCalledWith('entry-1');
        expect(wrapper.vm.showDeleteModal).toBeNull();
    });
});
