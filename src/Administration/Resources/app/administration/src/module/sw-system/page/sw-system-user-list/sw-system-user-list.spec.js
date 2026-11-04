/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

const STORE_KEY = 'grid.filter.system-user';

const mergeWithStoredFilters = jest.fn((storeKey, criteria) => Promise.resolve(criteria));

function createUserCollection(rows) {
    return new EntityCollection('user', 'user', Shopwell.Context.api, new Criteria(1), rows, rows.length);
}

function createRoleCollection(rows) {
    return new EntityCollection('acl_role', 'acl_role', Shopwell.Context.api, new Criteria(1), rows, rows.length);
}

Shopwell.Service().register('filterService', () => {
    return { mergeWithStoredFilters };
});

/**
 * Logs a user in without breaking the other store lookups of the component.
 */
function loginAs(userId) {
    const getStore = Shopwell.Store.get.bind(Shopwell.Store);

    jest.spyOn(Shopwell.Store, 'get').mockImplementation((key) => {
        if (key === 'session') {
            return { currentUser: { id: userId } };
        }

        return getStore(key);
    });
}

async function createWrapper({ rows = [], roles = [], privileges = [], isSso = false, term = '' } = {}) {
    const userRepository = {
        search: jest.fn(() => Promise.resolve(createUserCollection(rows))),
        save: jest.fn(() => Promise.resolve()),
        delete: jest.fn(() => Promise.resolve()),
    };

    const roleRepository = {
        search: jest.fn(() => Promise.resolve(createRoleCollection(roles))),
    };

    const query = { page: 1, limit: 25 };

    if (term) {
        query.term = term;
    }

    const wrapper = mount(
        await wrapTestComponent('sw-system-user-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t: (key) => key,
                    $route: {
                        query,
                        meta: { $module: { icon: 'regular-user-shield' } },
                    },
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
                    filterFactory: {
                        create: (entityName, filters) => {
                            return Object.entries(filters).map(([key, filter]) => {
                                return { ...filter, name: key };
                            });
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
                        isValidTerm: (searchTerm) => {
                            return searchTerm && searchTerm.trim().length >= 1;
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                            <div>
                                <slot name="search-bar"></slot>
                                <slot name="smart-bar-header"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                                <slot name="sidebar"></slot>
                                <slot></slot>
                            </div>`,
                    },
                    'sw-search-bar': true,
                    'sw-entity-listing': {
                        name: 'SwEntityListing',
                        template: `
                            <div>
                                <template v-for="item in dataSource || []" :key="item.id">
                                    <slot name="column-username" v-bind="{ item }"></slot>
                                    <slot name="column-aclRoles" v-bind="{ item }"></slot>
                                    <slot name="more-actions" v-bind="{ item }"></slot>
                                    <slot name="delete-action" v-bind="{ item }"></slot>
                                </template>
                                <slot name="pagination"></slot>
                            </div>`,
                        props: ['dataSource', 'columns', 'repository', 'sortBy', 'isLoading'],
                    },
                    'sw-pagination': true,
                    'sw-sidebar': true,
                    'sw-sidebar-item': true,
                    'sw-sidebar-filter-panel': {
                        name: 'SwSidebarFilterPanel',
                        template: `
                            <div class="sw-sidebar-filter-panel-stub">
                                <span
                                    v-for="filter in filters"
                                    :key="filter.name"
                                    class="sw-sidebar-filter-panel-stub__filter"
                                >{{ filter.name }}</span>
                            </div>`,
                        props: ['filters', 'storeKey', 'defaults', 'activeFilterNumber'],
                    },
                    'sw-context-menu-item': {
                        template: '<div class="sw-context-menu-item-stub" :data-disabled="disabled"><slot /></div>',
                        props: ['disabled', 'routerLink', 'variant'],
                    },
                    'mt-button': {
                        template: '<button class="mt-button-stub"><slot /></button>',
                    },
                    'mt-link': {
                        template: '<a class="mt-link-stub"><slot /></a>',
                    },
                    'mt-badge': {
                        template: '<span class="mt-badge-stub"><slot /></span>',
                    },
                    'mt-empty-state': {
                        name: 'MtEmptyState',
                        template: '<div class="mt-empty-state-stub">{{ headline }}<slot name="button"></slot></div>',
                        props: ['headline', 'description', 'icon'],
                    },
                    'sw-avatar': true,
                    'sw-time-ago': true,
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

    beforeEach(() => {
        mergeWithStoredFilters.mockClear();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
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

    it('should merge the stored filters of the user list into the search criteria', async () => {
        const { wrapper: userList } = await createWrapper();
        wrapper = userList;

        expect(mergeWithStoredFilters).toHaveBeenCalledWith(STORE_KEY, expect.anything());
    });

    it('should provide the role and status filters mapped to the entity fields', async () => {
        const { wrapper: userList } = await createWrapper();
        wrapper = userList;

        const filterPanel = userList.findComponent({ name: 'SwSidebarFilterPanel' });

        expect(filterPanel.props('storeKey')).toBe(STORE_KEY);
        expect(filterPanel.props('defaults')).toEqual(['role-filter', 'status-filter']);
        expect(filterPanel.props('activeFilterNumber')).toBe(0);
        expect(filterPanel.props('filters')).toEqual([
            {
                name: 'role-filter',
                property: 'aclRoles',
                label: 'sw-system.users.filter.labelRole',
                placeholder: 'sw-system.users.filter.labelRole',
            },
            {
                name: 'status-filter',
                property: 'active',
                label: 'sw-system.users.filter.labelStatus',
            },
        ]);
    });

    it('should render the users columns with the created at column at the end', async () => {
        const rows = [{ id: 'user-1', username: 'zhangsan', active: true }];
        const { wrapper: userList } = await createWrapper({ rows });
        wrapper = userList;

        const columns = userList.findComponent({ name: 'SwEntityListing' }).props('columns');

        expect(columns.map((column) => column.property)).toEqual(['username', 'aclRoles', 'email', 'active', 'createdAt']);
        expect(columns.find((column) => column.property === 'createdAt').label).toBe(
            'sw-system.users.user-grid.labelCreatedAt',
        );
    });

    it('should show the empty state for an empty user list', async () => {
        const { wrapper: userList } = await createWrapper();
        wrapper = userList;

        expect(userList.findComponent({ name: 'MtEmptyState' }).props('headline')).toBe(
            'sw-system.users.emptyState.noUserTitle',
        );
    });

    it('should show the no result empty state when a search term is active', async () => {
        const { wrapper: userList } = await createWrapper({ term: 'lisi' });
        wrapper = userList;

        expect(userList.findComponent({ name: 'MtEmptyState' }).props('headline')).toBe(
            'sw-system.users.emptyState.noResultTitle',
        );
    });

    it('should deny disabling the currently logged in user', async () => {
        loginAs('current-user-id');

        const createNotification = jest
            .spyOn(Shopwell.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        const rows = [{ id: 'current-user-id', username: 'admin', active: true }];
        const { wrapper: userList, userRepository } = await createWrapper({ rows });
        wrapper = userList;

        await userList.find('.sw-system-user-list__user-toggle-active-action').trigger('click');
        await flushPromises();

        expect(userRepository.save).not.toHaveBeenCalled();
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-system.users.user-grid.notification.toggleActiveSelfError.message',
            }),
        );
    });

    it('should persist the toggled active state', async () => {
        loginAs('current-user-id');

        const rows = [{ id: 'other-user-id', username: 'lisi', active: true }];
        const { wrapper: userList, userRepository } = await createWrapper({ rows });
        wrapper = userList;

        await userList.find('.sw-system-user-list__user-toggle-active-action').trigger('click');
        await flushPromises();

        expect(userRepository.save).toHaveBeenCalledWith(expect.objectContaining({ id: 'other-user-id', active: false }));
    });

    it('should ask for the password before deleting a user', async () => {
        loginAs('current-user-id');

        const rows = [{ id: 'other-user-id', username: 'lisi', active: true }];
        const { wrapper: userList } = await createWrapper({ rows });
        wrapper = userList;

        await userList.find('.sw-system-user-list__user-delete-action').trigger('click');
        await flushPromises();

        const confirmModal = userList.find('.sw-system-user-list__delete-modal');

        expect(confirmModal.exists()).toBe(true);

        const deleteButton = confirmModal.findAll('button').at(1);

        await deleteButton.trigger('click');
        await flushPromises();

        expect(userList.find('sw-verify-user-modal-stub').exists()).toBe(true);
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

        expect(userList.findAll('.mt-badge-stub')).toHaveLength(4);
        expect(userList.find('.sw-system-user-list__role-badge-more').text()).toBe('+1');
    });
});
