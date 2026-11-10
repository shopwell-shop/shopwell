/**
 * @sw-package framework
 */
import MtSwitch from '@shopwell-ag/meteor-component-library/dist/esm/MtSwitch';
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
                                Promise.resolve(
                                    Object.assign(
                                        [
                                            { id: 'org-id', name: 'Shopwell HQ', active: true },
                                        ],
                                        {
                                            total: 1,
                                            aggregations: {
                                                unitCounts: { buckets: [{ key: 'org-id', unitCount: { count: 2 } }] },
                                            },
                                        },
                                    ),
                                ),
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
                    'mt-switch': MtSwitch,
                    'sw-ignore-class': true,
                    'sw-entity-listing': {
                        name: 'sw-entity-listing',
                        props: [
                            'items',
                            'dataSource',
                            'allowEdit',
                            'allowInlineEdit',
                            'allowDelete',
                        ],
                        template: `
                    <div>
                        <div v-for="item in (dataSource || items)" :key="item.id" :data-row="item.id"><span>{{ item.name }}</span><span class="unit-count"><slot name="column-units" :item="item" /></span>
                            <slot name="column-active" :item="item" /><slot name="actions" v-bind="{ item }"></slot>
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
                    teleport: true,
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

    afterEach(() => wrapper.unmount());

    it('passes explicit false permissions to the entity listing', () => {
        const listing = wrapper.findComponent({ name: 'sw-entity-listing' });

        expect(listing.props('allowEdit')).toBe(false);
        expect(listing.props('allowInlineEdit')).toBe(false);
        expect(listing.props('allowDelete')).toBe(false);
    });

    it('persists disabling an organization with the real Meteor switch', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        await wrapper.get('[data-row="org-id"] .mt-switch input').setValue(false);
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(expect.objectContaining({ active: false }));
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

    it('opens the create page without asking for a name or writing an entity', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();

        await wrapper.get('.sw-settings-organization-list__add-organization').trigger('click');

        expect(routerPushSpy).toHaveBeenCalledWith({ name: 'sw.settings.organization.create' });
        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-organization-list__create-modal').exists()).toBe(false);
    });

    it('keeps deletion pending until the request finishes and allows retry after failure', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();
        let rejectDelete!: (error: Error) => void;
        deleteSpy.mockImplementationOnce(
            () =>
                new Promise<void>((_resolve, reject) => {
                    rejectDelete = reject;
                }),
        );
        await wrapper.get('.sw-entity-listing__context-menu-delete').trigger('click');
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.findAll('.sw-modal button').every((button) => button.attributes('disabled') !== undefined)).toBe(
            true,
        );
        rejectDelete(new Error('Delete failed'));
        await flushPromises();
        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        await wrapper.findAll('.sw-modal button')[1].trigger('click');
        await flushPromises();
        expect(deleteSpy).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-modal').exists()).toBe(false);
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
