/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type DictionaryEntryRow = {
    id: string;
    technicalName: string;
    position: number;
    active: boolean;
    translated: { label: string };
};

let saveSpy: jest.Mock;
let deleteSpy: jest.Mock;
let searchSpy: jest.Mock;

async function createWrapper(privileges: string[] = [], entries: DictionaryEntryRow[] = []): Promise<VueWrapper> {
    saveSpy = jest.fn(() => Promise.resolve());
    deleteSpy = jest.fn(() => Promise.resolve());
    searchSpy = jest.fn(() => Promise.resolve(entries));
    return shallowMount(
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
                                    technicalName: 'gender',
                                    translated: { label: 'Gender' },
                                }),
                            search: searchSpy,
                            save: saveSpy,
                            delete: deleteSpy,
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
                        props: ['modelValue'],
                        emits: ['update:modelValue'],
                        template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
                    },
                    'mt-number-field': {
                        props: ['modelValue'],
                        emits: ['update:modelValue'],
                        template: `<input type="number" :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))" />`,
                    },
                    'mt-switch': {
                        props: ['modelValue'],
                        emits: ['update:modelValue'],
                        template: `<input type="checkbox" :checked="modelValue" @change="$emit('update:modelValue', $event.target.checked)" />`,
                    },
                    'sw-ignore-class': true,
                    'sw-entity-listing': {
                        props: ['items', 'dataSource'],
                        template: `
                    <div>
                        <div v-for="item in (dataSource || items)" :key="item.id" :data-row="item.id"><span>{{ item.translated?.label }}</span>
                            <slot name="actions" v-bind="{ item }"></slot>
                            <slot name="action-modals" v-bind="{ item }"></slot></div>
                    </div>`,
                    },
                    'sw-language-switch': true,
                    'sw-context-menu-item': {
                        props: ['disabled'],
                        template: '<button :disabled="disabled"><slot /></button>',
                    },
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
    );
}

describe('module/sw-settings-dictionary/page/sw-settings-dictionary-detail', () => {
    let wrapper: VueWrapper;

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
        expect(wrapper.get('h2').text()).toContain('Gender');
        expect(searchSpy).toHaveBeenCalledWith(
            expect.objectContaining({ sortings: [expect.objectContaining({ field: 'position', order: 'ASC' })] }),
        );
    });

    it('should save a new entry with the payload of the current language', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-dictionary-detail__add-entry').trigger('click');

        expect(wrapper.find('.sw-settings-dictionary-detail__create-modal').exists()).toBe(true);

        await wrapper.get('.sw-settings-dictionary-detail__create-label').setValue('男性');
        await wrapper.get('.sw-settings-dictionary-detail__create-technical-name').setValue('male');
        await wrapper.get('.sw-settings-dictionary-detail__create-position').setValue(10);
        await wrapper.get('.sw-settings-dictionary-detail__create-active').setValue(true);

        await wrapper.findAll('.sw-settings-dictionary-detail__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                dictionaryTypeId: 'type-id',
                technicalName: 'male',
                position: 10,
                active: true,
                label: '男性',
            }),
        );
        expect(wrapper.find('.sw-settings-dictionary-detail__create-modal').exists()).toBe(false);
    });

    it('should reject an entry whose technical name duplicates an existing entry', async () => {
        wrapper = await createWrapper(
            ['dictionary.creator'],
            [
                {
                    id: 'entry-1',
                    technicalName: 'male',
                    position: 10,
                    active: true,
                    translated: { label: '男性' },
                },
            ],
        );
        await flushPromises();

        await wrapper.get('.sw-settings-dictionary-detail__add-entry').trigger('click');

        await wrapper.get('.sw-settings-dictionary-detail__create-label').setValue('男性');
        await wrapper.get('.sw-settings-dictionary-detail__create-technical-name').setValue('male');

        await wrapper.findAll('.sw-settings-dictionary-detail__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-dictionary-detail__create-modal').exists()).toBe(true);
    });

    it('should pre-fill the position with the next free slot', async () => {
        wrapper = await createWrapper(
            ['dictionary.creator'],
            [
                {
                    id: 'entry-1',
                    technicalName: 'male',
                    position: 10,
                    active: true,
                    translated: { label: '男性' },
                },
            ],
        );
        await flushPromises();

        await wrapper.get('.sw-settings-dictionary-detail__add-entry').trigger('click');

        expect((wrapper.get('.sw-settings-dictionary-detail__create-position').element as HTMLInputElement).value).toBe(
            '20',
        );
    });

    it('should confirm the delete of an entry', async () => {
        wrapper = await createWrapper(
            ['dictionary.deleter'],
            [{ id: 'entry-1', technicalName: 'male', position: 10, active: true, translated: { label: '男性' } }],
        );
        await flushPromises();

        await wrapper.get('.sw-entity-listing__context-menu-delete').trigger('click');
        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('entry-1');
        expect(wrapper.find('.sw-modal').exists()).toBe(false);
    });
});
