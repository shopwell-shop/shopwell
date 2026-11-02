/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

function createRoleCollection(rows) {
    return new EntityCollection('acl_role', 'acl_role', Shopwell.Context.api, new Criteria(1), rows, rows.length);
}

async function createWrapper({ rows = [], affectedUsers = 0, privileges = [], isSso = false } = {}) {
    const roleRepository = {
        search: jest.fn(() => Promise.resolve(createRoleCollection(rows))),
        delete: jest.fn(() => Promise.resolve()),
    };

    const userRepository = {
        searchIds: jest.fn(() => Promise.resolve({ data: [], total: affectedUsers })),
    };

    const router = { push: jest.fn(), replace: jest.fn() };

    const wrapper = mount(
        await wrapTestComponent('sw-system-role-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t: (key) => key,
                    $route: { query: {}, meta: { $module: { icon: 'regular-lock' } } },
                    $router: router,
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    repositoryFactory: {
                        create: (entity) => {
                            return entity === 'user' ? userRepository : roleRepository;
                        },
                    },
                    ssoSettingsService: {
                        isSso: () => Promise.resolve({ isSso }),
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                            <div>
                                <slot name="smart-bar-header"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                            </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-data-grid': {
                        template: '<div><slot name="pagination"></slot><slot name="action-modals"></slot></div>',
                        props: ['dataSource', 'columns', 'isLoading'],
                    },
                    'sw-context-menu-item': {
                        template: '<div class="sw-context-menu-item-stub"><slot /></div>',
                        props: ['disabled', 'routerLink', 'variant'],
                    },
                    'sw-simple-search-field': {
                        template: '<input class="search-field" />',
                        inheritAttrs: false,
                        props: ['value', 'placeholder'],
                    },
                    'mt-button': {
                        template: '<button class="mt-button-stub"><slot /></button>',
                    },
                    'mt-link': {
                        template: '<a class="mt-link-stub"><slot /></a>',
                    },
                    'mt-empty-state': {
                        template: '<div class="mt-empty-state-stub">{{ headline }}<slot name="button"></slot></div>',
                        props: ['headline', 'description', 'icon'],
                    },
                    'sw-pagination': true,
                    'sw-verify-user-modal': true,
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, roleRepository, userRepository, router };
}

describe('module/sw-system/page/sw-system-role-list', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }

        jest.restoreAllMocks();
    });

    it('should only load the roles that are neither app owned nor deleted', async () => {
        const { wrapper: roleList, roleRepository } = await createWrapper();
        wrapper = roleList;

        const criteria = roleRepository.search.mock.calls[0][0];

        expect(criteria.filters).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ field: 'app.id', value: null }),
                expect.objectContaining({ field: 'deletedAt', value: null }),
            ]),
        );
    });

    it('should expose the privilege count as a column', async () => {
        const { wrapper: roleList } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management', description: '', privileges: ['order.viewer', 'order.editor'] }],
        });
        wrapper = roleList;

        expect(roleList.vm.rolesColumns.map((column) => column.property)).toEqual(['name', 'description', 'privileges']);
        expect(roleList.vm.roles.at(0).privileges).toHaveLength(2);
    });

    it('should start the role detail in duplicate mode', async () => {
        const { wrapper: roleList, router } = await createWrapper();
        wrapper = roleList;

        roleList.vm.onDuplicateRole({ id: 'role-1' });

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.system.role.detail',
            query: { copyFrom: 'role-1' },
        });
    });

    it('should count the affected users before deleting a role', async () => {
        const { wrapper: roleList, userRepository } = await createWrapper({ affectedUsers: 3 });
        wrapper = roleList;

        roleList.vm.onDelete({ id: 'role-1', name: 'Order management' });

        await flushPromises();

        expect(userRepository.searchIds).toHaveBeenCalledTimes(1);
        expect(roleList.vm.affectedUserCount).toBe(3);
        expect(roleList.vm.isConfirmDeleteModalOpen).toBe(true);
    });

    it('should NOT open role detail of a new role with an id', async () => {
        const { wrapper: roleList, router } = await createWrapper();
        wrapper = roleList;

        roleList.vm.onCreateRole();

        expect(router.push).toHaveBeenCalledWith({ name: 'sw.system.role.detail' });
    });
});
