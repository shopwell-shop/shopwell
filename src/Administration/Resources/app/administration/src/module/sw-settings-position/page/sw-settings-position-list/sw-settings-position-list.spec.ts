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
                            save: saveSpy,
                            delete: deleteSpy,
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

describe('module/sw-settings-position/page/sw-settings-position-list', () => {
    let wrapper: VueWrapper;

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
        expect(wrapper.findAll('[data-row]')).toHaveLength(1);
        expect(wrapper.get('.row-name').text()).toBe('Manager');
    });

    it('should save a new position with sort order and active flag via the modal', async () => {
        wrapper = await createWrapper(['position.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-position-list__add-position').trigger('click');

        expect(wrapper.find('.sw-settings-position-list__edit-modal').exists()).toBe(true);

        await wrapper.get('.sw-settings-position-list__edit-name').setValue('Manager');
        await wrapper.get('.sw-settings-position-list__edit-position').setValue(10);
        await wrapper.get('.sw-settings-position-list__edit-active').setValue(true);

        await wrapper.findAll('.sw-settings-position-list__edit-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Manager',
                position: 10,
                active: true,
            }),
        );
        expect(wrapper.find('.sw-settings-position-list__edit-modal').exists()).toBe(false);
    });

    it('should reject a position without a name', async () => {
        wrapper = await createWrapper(['position.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-position-list__add-position').trigger('click');

        await wrapper.findAll('.sw-settings-position-list__edit-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-position-list__edit-modal').exists()).toBe(true);
    });

    it('should not allow deleting without permission', () => {
        const deleteMenuItem = wrapper.find('.sw-entity-listing__context-menu-delete');

        expect(deleteMenuItem.attributes('disabled')).toBeDefined();
    });

    it('should allow deleting with deleter permission and confirm the delete', async () => {
        wrapper = await createWrapper(['position.deleter']);
        await flushPromises();

        await wrapper.get('.sw-entity-listing__context-menu-delete').trigger('click');
        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('position-1');
    });
});
