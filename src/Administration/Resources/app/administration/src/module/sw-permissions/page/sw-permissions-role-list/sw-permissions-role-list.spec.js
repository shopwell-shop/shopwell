/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
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
    const route = reactive({ query: {}, meta: { $module: { icon: 'regular-lock' } } });

    const wrapper = mount(
        await wrapTestComponent('sw-permissions-role-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t: (key, values) => (values ? `${key} ${JSON.stringify(values)}` : key),
                    $route: route,
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
                                <header class="page-search"><slot name="search-bar"></slot></header>
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
                        name: 'SwDataGrid',
                        template: `<div>
                            <article v-for="item in dataSource" :key="item.id" :data-row="item.id">
                                <slot name="column-name" :item="item" />
                                <slot name="column-privileges" :item="item" />
                                <slot name="actions" :item="item" />
                                <slot name="action-modals" :item="item" />
                            </article>
                            <slot name="pagination" />
                        </div>`,
                        props: ['dataSource', 'columns', 'isLoading'],
                    },
                    'sw-context-menu-item': {
                        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                        props: ['disabled', 'routerLink', 'variant'],
                        emits: ['click'],
                    },
                    'sw-simple-search-field': {
                        template: '<input class="search-field" />',
                        inheritAttrs: false,
                        props: ['value', 'placeholder'],
                    },
                    'sw-search-bar': {
                        name: 'SwSearchBar',
                        template: '<input class="global-search-field" />',
                        props: ['initialSearchType', 'initialSearch'],
                        emits: ['search'],
                    },
                    'mt-button': {
                        template: '<button class="mt-button-stub" :disabled="disabled"><slot /></button>',
                        props: ['disabled'],
                    },
                    'mt-link': {
                        template: '<a class="mt-link-stub"><slot /></a>',
                    },
                    'mt-empty-state': {
                        template: '<div class="mt-empty-state-stub">{{ headline }}<slot name="button"></slot></div>',
                        props: ['headline', 'description', 'icon'],
                    },
                    'sw-pagination': true,
                    'sw-permissions-role-info-modal': true,
                    'sw-modal': {
                        template: '<div><slot /><slot name="modal-footer" /></div>',
                    },
                    'sw-verify-user-modal': {
                        emits: ['verified'],
                        template:
                            '<button class="verify-role" @click="$emit(\'verified\', { token: \'verified\' })">Verify</button>',
                    },
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, roleRepository, userRepository, router, route };
}

describe('module/sw-permissions/page/sw-permissions-role-list', () => {
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
            rows: [
                { id: 'role-1', name: 'Order management', description: '', privileges: ['order.viewer', 'order.editor'] },
            ],
        });
        wrapper = roleList;

        const grid = roleList.getComponent({ name: 'SwDataGrid' });
        expect(grid.props('columns').map((column) => column.property)).toEqual(['name', 'description', 'privileges']);
        expect(roleList.get('.sw-permissions-role-list__privilege-count').text()).toBe('2');
    });

    it('searches roles from the shared page header and resets pagination when searching or clearing', async () => {
        const { wrapper: roleList, roleRepository } = await createWrapper();
        wrapper = roleList;

        const search = roleList.getComponent({ name: 'SwSearchBar' });
        expect(roleList.get('.page-search').find('.global-search-field').exists()).toBe(true);
        expect(search.props('initialSearchType')).toBe('acl_role');
        expect(roleList.findComponent('sw-simple-search-field-stub').exists()).toBe(false);
        expect(roleList.find('.search-field').exists()).toBe(false);

        await roleList.setData({ page: 3 });
        search.vm.$emit('search', 'manager');
        await flushPromises();

        expect(roleRepository.search.mock.lastCall[0].term).toBe('manager');
        expect(roleRepository.search.mock.lastCall[0].getPage()).toBe(1);
        expect(search.props('initialSearch')).toBe('manager');

        await roleList.setData({ page: 2 });
        search.vm.$emit('search', '');
        await flushPromises();

        expect(roleRepository.search.mock.lastCall[0].term).toBeNull();
        expect(roleRepository.search.mock.lastCall[0].getPage()).toBe(1);
        expect(roleRepository.delete).not.toHaveBeenCalled();
    });

    it('should open the creation dialog with the role to duplicate', async () => {
        const { wrapper: roleList, router } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            privileges: ['permissions.creator'],
        });
        wrapper = roleList;

        await roleList.get('.sw-permissions-role-list__context-menu-duplicate').trigger('click');

        expect(roleList.getComponent('sw-permissions-role-info-modal-stub').attributes('copy-from-id')).toBe('role-1');
        expect(router.push).not.toHaveBeenCalled();
    });

    it('should count the affected users before deleting a role', async () => {
        const { wrapper: roleList, userRepository } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            affectedUsers: 3,
            privileges: ['permissions.deleter'],
        });
        wrapper = roleList;

        await roleList.get('.sw-permissions-role-list__context-menu-delete').trigger('click');
        await flushPromises();

        expect(userRepository.searchIds).toHaveBeenCalledTimes(1);
        expect(userRepository.searchIds.mock.calls[0][0].filters).toContainEqual(
            expect.objectContaining({ field: 'aclRoles.id', value: 'role-1' }),
        );
        expect(roleList.get('.sw-permissions-role-list__delete-modal').exists()).toBe(true);
        expect(roleList.get('.sw-permissions-role-list__affected-users').text()).toContain('"count":3');
    });

    it('opens a dialog before navigating and only enters permissions after creation', async () => {
        const { wrapper: roleList, router } = await createWrapper({ privileges: ['permissions.creator'] });
        wrapper = roleList;

        await roleList.get('.sw-permissions-role-list__button-create').trigger('click');

        expect(router.push).not.toHaveBeenCalled();
        const dialog = roleList.getComponent('sw-permissions-role-info-modal-stub');
        dialog.vm.$emit('created', 'created-role');
        await flushPromises();
        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.permissions.role.detail.menus',
            params: { id: 'created-role' },
        });
    });

    it('opens the dialog when a legacy create link returns to the mounted list', async () => {
        const { wrapper: roleList, route, router } = await createWrapper({ privileges: ['permissions.creator'] });
        wrapper = roleList;
        route.query = { create: 'true', copyFrom: 'source-role', page: '2' };
        await flushPromises();
        expect(roleList.getComponent('sw-permissions-role-info-modal-stub').attributes('copy-from-id')).toBe('source-role');
        expect(router.replace).toHaveBeenCalledWith({ name: 'sw.permissions.role', query: { page: '2' } });
    });

    it.each([false, true])('should delete the selected role after confirmation with SSO=%s', async (isSso) => {
        const { wrapper: roleList, roleRepository } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            privileges: ['permissions.deleter'],
            isSso,
        });
        wrapper = roleList;

        await roleList.get('.sw-permissions-role-list__context-menu-delete').trigger('click');
        await flushPromises();
        await roleList.get('.sw-permissions-role-list__confirm-delete-button').trigger('click');
        await flushPromises();

        if (!isSso) {
            await roleList.get('.verify-role').trigger('click');
            await flushPromises();
        }

        expect(roleRepository.delete).toHaveBeenCalledWith('role-1', expect.any(Object));
    });
});
