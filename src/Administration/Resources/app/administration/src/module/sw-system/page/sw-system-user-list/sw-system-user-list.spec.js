/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

function createUserCollection(rows) {
    return new EntityCollection('user', 'user', Shopwell.Context.api, new Criteria(1), rows, rows.length);
}

function createRoleCollection(rows) {
    return new EntityCollection('acl_role', 'acl_role', Shopwell.Context.api, new Criteria(1), rows, rows.length);
}

async function createWrapper({ rows = [], roles = [], privileges = [], isSso = false } = {}) {
    const userRepository = {
        search: jest.fn(() => Promise.resolve(createUserCollection(rows))),
        save: jest.fn(() => Promise.resolve()),
        delete: jest.fn(() => Promise.resolve()),
    };

    const roleRepository = {
        search: jest.fn(() => Promise.resolve(createRoleCollection(roles))),
    };

    const wrapper = mount(
        await wrapTestComponent('sw-system-user-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t: (key) => key,
                    $route: { query: {}, meta: { $module: { icon: 'regular-user-shield' } } },
                    $router: { push: jest.fn(), replace: jest.fn() },
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
                    userService: {},
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
                        template: '<div class="sw-context-menu-item-stub" :data-disabled="disabled"><slot /></div>',
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
                    'sw-avatar': true,
                    'sw-pagination': true,
                    'sw-system-user-sso-status-label': true,
                    'sw-system-user-sso-invitation-modal': true,
                    'sw-verify-user-modal': true,
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, userRepository, roleRepository };
}

describe('module/sw-system/page/sw-system-user-list', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }

        jest.restoreAllMocks();
    });

    it('should load the users with the role and avatar associations sorted by username', async () => {
        const { wrapper: userList, userRepository } = await createWrapper();
        wrapper = userList;

        expect(userRepository.search).toHaveBeenCalledTimes(1);

        const criteria = userRepository.search.mock.calls[0][0];

        expect(criteria.associations.map((association) => association.association)).toEqual(
            expect.arrayContaining(['aclRoles', 'avatarMedia']),
        );
        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'username', order: 'ASC' })]);
    });

    it('should filter the users by the selected role', async () => {
        const { wrapper: userList, userRepository } = await createWrapper({
            roles: [{ id: 'role-1', name: 'Order management' }],
        });
        wrapper = userList;

        userRepository.search.mockClear();
        userList.vm.roleFilter = 'role-1';
        userList.vm.onFilterChange();

        const criteria = userRepository.search.mock.calls[0][0];

        expect(criteria.filters).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ field: 'aclRoles.id', value: 'role-1' }),
            ]),
        );
    });

    it('should translate the status filter into the active flag', async () => {
        const { wrapper: userList, userRepository } = await createWrapper();
        wrapper = userList;

        userRepository.search.mockClear();
        userList.vm.statusFilter = 'inactive';
        userList.vm.onFilterChange();

        const criteria = userRepository.search.mock.calls[0][0];

        expect(criteria.filters).toEqual(
            expect.arrayContaining([expect.objectContaining({ field: 'active', value: false })]),
        );
    });

    it('should deny disabling the currently logged in user', async () => {
        jest.spyOn(Shopwell.Store, 'get').mockReturnValue({ currentUser: { id: 'current-user-id' } });

        const { wrapper: userList, userRepository } = await createWrapper();
        wrapper = userList;

        const createNotificationError = jest.spyOn(userList.vm, 'createNotificationError').mockImplementation(() => {});
        const self = { id: 'current-user-id', username: 'admin', active: true };

        userList.vm.onToggleActive(self);

        expect(userRepository.save).not.toHaveBeenCalled();
        expect(createNotificationError).toHaveBeenCalled();
        expect(self.active).toBe(true);
    });

    it('should persist the toggled active state', async () => {
        jest.spyOn(Shopwell.Store, 'get').mockReturnValue({ currentUser: { id: 'current-user-id' } });

        const { wrapper: userList, userRepository } = await createWrapper();
        wrapper = userList;

        jest.spyOn(userList.vm, 'createNotificationSuccess').mockImplementation(() => {});
        jest.spyOn(userList.vm, 'getList').mockImplementation(() => {});

        const user = { id: 'other-user-id', username: 'lisi', active: true };

        userList.vm.onToggleActive(user);

        expect(userRepository.save).toHaveBeenCalledWith(expect.objectContaining({ id: 'other-user-id', active: false }));
        expect(user.active).toBe(false);
    });

    it('should render the roles of a user as badges and collapse the rest', async () => {
        const rows = [
            {
                id: 'user-1',
                username: 'zhangsan',
                name: '张三',
                email: 'zhangsan@example.com',
                active: true,
                aclRoles: [
                    { id: 'role-1', name: 'Order' },
                    { id: 'role-2', name: 'Support' },
                    { id: 'role-3', name: 'Content' },
                    { id: 'role-4', name: 'Marketing' },
                ],
            },
        ];

        const { wrapper: userList } = await createWrapper({ rows });
        wrapper = userList;

        expect(userList.vm.userColumns.map((column) => column.property)).toEqual(['username', 'aclRoles', 'email', 'active']);
        expect(userList.vm.hasUsers).toBe(true);
    });
});
