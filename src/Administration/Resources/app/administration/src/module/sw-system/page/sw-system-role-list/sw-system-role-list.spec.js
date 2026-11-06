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
                    $t: (key, values) => (values ? `${key} ${JSON.stringify(values)}` : key),
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
            rows: [
                { id: 'role-1', name: 'Order management', description: '', privileges: ['order.viewer', 'order.editor'] },
            ],
        });
        wrapper = roleList;

        const grid = roleList.getComponent({ name: 'SwDataGrid' });
        expect(grid.props('columns').map((column) => column.property)).toEqual(['name', 'description', 'privileges']);
        expect(roleList.get('.sw-system-role-list__privilege-count').text()).toBe('2');
    });

    it('should start the role detail in duplicate mode', async () => {
        const { wrapper: roleList, router } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            privileges: ['users_and_permissions.creator'],
        });
        wrapper = roleList;

        await roleList.get('.sw-system-role-list__context-menu-duplicate').trigger('click');

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.system.role.detail',
            query: { copyFrom: 'role-1' },
        });
    });

    it('should count the affected users before deleting a role', async () => {
        const { wrapper: roleList, userRepository } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            affectedUsers: 3,
            privileges: ['users_and_permissions.deleter'],
        });
        wrapper = roleList;

        await roleList.get('.sw-system-role-list__context-menu-delete').trigger('click');
        await flushPromises();

        expect(userRepository.searchIds).toHaveBeenCalledTimes(1);
        expect(userRepository.searchIds.mock.calls[0][0].filters).toContainEqual(
            expect.objectContaining({ field: 'aclRoles.id', value: 'role-1' }),
        );
        expect(roleList.get('.sw-system-role-list__delete-modal').exists()).toBe(true);
        expect(roleList.get('.sw-system-role-list__affected-users').text()).toContain('"count":3');
    });

    it('should NOT open role detail of a new role with an id', async () => {
        const { wrapper: roleList, router } = await createWrapper({ privileges: ['users_and_permissions.creator'] });
        wrapper = roleList;

        await roleList.get('.sw-system-role-list__button-create').trigger('click');

        expect(router.push).toHaveBeenCalledWith({ name: 'sw.system.role.detail' });
    });

    it.each([false, true])('should delete the selected role after confirmation with SSO=%s', async (isSso) => {
        const { wrapper: roleList, roleRepository } = await createWrapper({
            rows: [{ id: 'role-1', name: 'Order management' }],
            privileges: ['users_and_permissions.deleter'],
            isSso,
        });
        wrapper = roleList;

        await roleList.get('.sw-system-role-list__context-menu-delete').trigger('click');
        await flushPromises();
        await roleList.get('.sw-system-role-list__confirm-delete-button').trigger('click');
        await flushPromises();

        if (!isSso) {
            await roleList.get('.verify-role').trigger('click');
            await flushPromises();
        }

        expect(roleRepository.delete).toHaveBeenCalledWith('role-1', expect.any(Object));
    });
});
