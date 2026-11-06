/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let saveSpy: jest.Mock;
let deleteSpy: jest.Mock;

async function createWrapper(privileges: string[] = []): Promise<VueWrapper> {
    saveSpy = jest.fn(() => Promise.resolve());
    deleteSpy = jest.fn(() => Promise.resolve());
    return shallowMount(
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
                                        technicalName: 'gender',
                                        translated: { label: 'Gender' },
                                        active: true,
                                        entries: [
                                            { id: 'entry-1', technicalName: 'male' },
                                            { id: 'entry-2', technicalName: 'female' },
                                        ],
                                    },
                                ]),
                            save: saveSpy,
                            delete: deleteSpy,
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
                        <div v-for="item in (dataSource || items)" :key="item.id" :data-row="item.id">
                            <span class="row-name">{{ item.name || item.translated?.label }}</span><span class="entry-count"><slot name="column-entries" :item="item" /></span>
                            <slot name="actions" v-bind="{ item }"></slot>
                            <slot name="action-modals" v-bind="{ item }"></slot>
                        </div>
                    </div>`,
                    },
                    'sw-language-switch': true,
                    'sw-search-bar': true,
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

describe('module/sw-settings-dictionary/page/sw-settings-dictionary-list', () => {
    let wrapper: VueWrapper;

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
        expect(wrapper.findAll('[data-row]')).toHaveLength(1);
        expect(wrapper.get('.entry-count').text()).toBe('2');
    });

    it('should create a new dictionary type with technical name and active flag', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-dictionary-list__add-type').trigger('click');

        expect(wrapper.find('.sw-settings-dictionary-list__create-modal').exists()).toBe(true);

        await wrapper.get('.sw-settings-dictionary-list__create-label').setValue('Gender');
        await wrapper.get('.sw-settings-dictionary-list__create-technical-name').setValue('gender');

        await wrapper.findAll('.sw-settings-dictionary-list__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                technicalName: 'gender',
                active: true,
            }),
        );
        expect(wrapper.find('.sw-settings-dictionary-list__create-modal').exists()).toBe(false);
    });

    it('should reject a new type without technical name', async () => {
        wrapper = await createWrapper(['dictionary.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-dictionary-list__add-type').trigger('click');
        await wrapper.get('.sw-settings-dictionary-list__create-label').setValue('Gender');

        await wrapper.findAll('.sw-settings-dictionary-list__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-dictionary-list__create-modal').exists()).toBe(true);
    });

    it('should confirm the delete of a type', async () => {
        wrapper = await createWrapper(['dictionary.deleter']);
        await flushPromises();

        await wrapper.get('.sw-entity-listing__context-menu-delete').trigger('click');
        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('type-id');
        expect(wrapper.find('.sw-modal').exists()).toBe(false);
    });
});
