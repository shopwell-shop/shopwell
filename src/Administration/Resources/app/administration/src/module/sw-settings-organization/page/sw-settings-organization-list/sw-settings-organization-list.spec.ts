/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type OrganizationVm = {
    organizations: Array<{
        id: string;
        name: string;
        active: boolean;
        units?: Array<{ id: string; parentId: string | null }>;
    }>;
    newOrganization: {
        id: string;
        name: string | null;
        active: boolean;
    };
    showDeleteModal: string | null;
    organizationRepository: {
        save: jest.Mock;
        delete: jest.Mock;
    };
    $router: { push: jest.Mock };
    getUnitCount: (item: { units?: unknown[] }) => number;
    onAddOrganization: () => void;
    onCreateSave: () => Promise<void>;
    onDelete: (id: string) => void;
    onConfirmDelete: (id: string) => Promise<void>;
};

type TypedWrapper = { vm: OrganizationVm } & VueWrapper;

async function createWrapper(privileges: string[] = []): Promise<TypedWrapper> {
    return mount(
        await wrapTestComponent('sw-settings-organization-list', {
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
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
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

describe('module/sw-settings-organization/page/sw-settings-organization-list', () => {
    let wrapper: TypedWrapper;

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
        expect(wrapper.vm.organizations).toHaveLength(1);
        expect(wrapper.vm.getUnitCount(wrapper.vm.organizations[0])).toBe(2);
    });

    it('should create an organization and route to its detail page', async () => {
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();

        const routerPushSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.$router.push = routerPushSpy;

        wrapper.vm.onAddOrganization();
        wrapper.vm.newOrganization.name = 'Shopwell HQ';

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.organizationRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

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

        wrapper.vm.onAddOrganization();

        const saveSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.organizationRepository.save = saveSpy;

        await wrapper.vm.onCreateSave();

        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('should confirm the delete of an organization', async () => {
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();

        const deleteSpy = jest.fn(() => Promise.resolve());
        wrapper.vm.organizationRepository.delete = deleteSpy;

        wrapper.vm.onDelete('org-id');
        expect(wrapper.vm.showDeleteModal).toBe('org-id');

        await wrapper.vm.onConfirmDelete('org-id');

        expect(deleteSpy).toHaveBeenCalledWith('org-id');
    });
});
