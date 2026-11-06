/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let saveSpy: jest.Mock;
let deleteSpy: jest.Mock;
let routerPushSpy: jest.Mock;

async function createWrapper(privileges: string[] = []): Promise<VueWrapper> {
    saveSpy = jest.fn(() => Promise.resolve());
    deleteSpy = jest.fn(() => Promise.resolve());
    routerPushSpy = jest.fn(() => Promise.resolve());
    return shallowMount(
        await wrapTestComponent('sw-settings-organization-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $router: { push: routerPushSpy },
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
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
                            search: () =>
                                Promise.resolve([
                                    {
                                        id: 'org-id',
                                        name: 'Shopwell HQ',
                                        active: true,
                                        units: [
                                            { id: 'unit-1', parentId: null },
                                            { id: 'unit-2', parentId: 'unit-1' },
                                        ],
                                    },
                                ]),
                            save: saveSpy,
                            delete: deleteSpy,
                            create: () => ({
                                id: 'new-org-id',
                                name: null,
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
                        <div v-for="item in (dataSource || items)" :key="item.id" :data-row="item.id"><span>{{ item.name }}</span><span class="unit-count"><slot name="column-units" :item="item" /></span>
                            <slot name="actions" v-bind="{ item }"></slot>
                            <slot name="action-modals" v-bind="{ item }"></slot></div>
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

describe('module/sw-settings-organization/page/sw-settings-organization-list', () => {
    let wrapper: VueWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should not allow creating an organization without permission', () => {
        const addButton = wrapper.find('.sw-settings-organization-list__add-organization');

        expect(addButton.attributes('disabled')).toBeDefined();
    });

    it('should allow creating an organization with creator permission', async () => {
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-organization-list__add-organization');

        expect(addButton.attributes('disabled')).toBeUndefined();
    });

    it('should load organizations with their unit count', () => {
        expect(wrapper.findAll('[data-row]')).toHaveLength(1);
        expect(wrapper.get('.unit-count').text()).toBe('2');
    });

    it('should create an organization and route to its detail page', async () => {
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-organization-list__add-organization').trigger('click');
        await wrapper.get('.sw-settings-organization-list__create-name').setValue('Shopwell HQ');
        await wrapper.findAll('.sw-settings-organization-list__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Shopwell HQ',
            }),
        );
        expect(routerPushSpy).toHaveBeenCalled();
    });

    it('should reject an organization without a name', async () => {
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-organization-list__add-organization').trigger('click');
        await wrapper.findAll('.sw-settings-organization-list__create-modal button')[1].trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('should confirm the delete of an organization', async () => {
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();

        await wrapper.get('.sw-entity-listing__context-menu-delete').trigger('click');
        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('org-id');
    });
});
