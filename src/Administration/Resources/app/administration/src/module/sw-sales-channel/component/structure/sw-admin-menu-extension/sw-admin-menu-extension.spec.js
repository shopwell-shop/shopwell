/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import useAdminLayout from 'src/app/composables/use-admin-layout';
import 'src/app/component/structure/sw-admin-menu';
import swAdminMenuExtension from 'src/module/sw-sales-channel/component/structure/sw-admin-menu-extension';
import createMenuService from 'src/app/service/menu.service';

// Turn off known errors
import { missingGetListMethod } from 'test/_helper_/allowedErrors';

Shopwell.Component.register('sw-admin-menu-extension', swAdminMenuExtension);

global.allowedErrors = [missingGetListMethod];

const menuService = createMenuService(Shopwell.Module);
Shopwell.Service().register('menuService', () => menuService);

async function createWrapper(route = { name: 'product.list', meta: {} }) {
    return mount(await wrapTestComponent('sw-admin-menu', { sync: true }), {
        global: {
            mocks: { $route: route },
            stubs: {
                'sw-admin-menu-topbar': await wrapTestComponent('sw-admin-menu-topbar', { sync: true }),
                'sw-version': true,
                'sw-loader': true,
                'sw-avatar': true,
                'sw-shortcut-overview': true,
                'sw-sales-channel-menu': true,
                'sw-admin-menu-item': true,
            },
            provide: {
                loginService: {
                    notifyOnLoginListener: () => {},
                },
                userService: {
                    getUser: () => Promise.resolve({ data: {} }),
                },
                menuService,
                appModulesService: {
                    fetchAppModules: () => Promise.resolve([]),
                },
                systemConfigApiService: {
                    getValues: () => Promise.resolve({}),
                },
                customEntityDefinitionService: {
                    getMenuEntries: () => {
                        return [];
                    },
                },
            },
        },
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeAll(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    beforeEach(async () => {
        global.ResizeObserver = class {
            observe() {}
            disconnect() {}
        };
        Shopwell.Store.get('session').setCurrentUser({ id: 'channel-layout-user' });
        Shopwell.Service('userConfigService').upsert.mockResolvedValue(undefined);
        await useAdminLayout().saveUserLayout('sidebar');
    });

    it('does not append sales channels to another module sidebar in mixed layout', async () => {
        global.activeAclRoles = ['sales_channel.viewer'];
        await useAdminLayout().saveUserLayout('mixed');
        const wrapper = await createWrapper();
        await wrapper.setData({ viewportWidth: 1600 });
        expect(wrapper.find('sw-sales-channel-menu-stub').exists()).toBe(false);
        expect(wrapper.find('[data-navigation-key="sw-sales-channel-layout"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('shows the channel sidebar only on channel routes in mixed layout', async () => {
        global.activeAclRoles = ['sales_channel.viewer'];
        await useAdminLayout().saveUserLayout('mixed');
        const wrapper = await createWrapper({
            name: 'sw.sales.channel.detail.base',
            meta: { $module: { entity: 'sales_channel' } },
        });
        await wrapper.setData({ viewportWidth: 1600 });
        expect(wrapper.find('.sw-admin-menu-topbar__sidebar').exists()).toBe(true);
        expect(wrapper.findAll('sw-sales-channel-menu-stub')).toHaveLength(1);
        wrapper.unmount();
    });

    it('should not show the sw-sales-channel-menu when privilege does not exist', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeFalsy();
    });

    it('should show the sw-sales-channel-menu when privilege exists', async () => {
        global.activeAclRoles = ['sales_channel.viewer'];
        const wrapper = await createWrapper();
        const swSalesChannelMenu = wrapper.find('sw-sales-channel-menu-stub');

        expect(swSalesChannelMenu.exists()).toBeTruthy();
    });
});
