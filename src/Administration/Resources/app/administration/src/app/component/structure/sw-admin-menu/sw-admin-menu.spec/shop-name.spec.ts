/** @sw-package framework */
import { config, flushPromises } from '@vue/test-utils';
import createWrapper from './create-wrapper';

describe('administration shop name permissions', () => {
    beforeAll(() => {
        Shopwell.Module.getModuleRegistry().clear();
        Shopwell.Store.get('session').currentLocale = 'en-GB';
        Shopwell.Context.app.fallbackLocale = 'en-GB';
    });

    it.each([false, true])('loads protected configuration only with read permission: %s', async (canRead) => {
        config.global.stubs = { ...config.global.stubs, transition: false };
        const getValues = jest.fn().mockResolvedValue({ 'core.basicInformation.shopName': 'My Shop' });
        const wrapper = await createWrapper(
            {},
            {
                systemConfigApiService: { getValues },
                acl: {
                    can: (privilege: string) =>
                        privilege === 'system_config:read' ? canRead : privilege !== 'shouldReturnFalse',
                },
            },
        );
        await flushPromises();
        expect(getValues).toHaveBeenCalledTimes(canRead ? 1 : 0);
        expect(wrapper.find('.sw-admin-menu__shop-name').text()).toBe(canRead ? 'My Shop' : 'Shopwell');
        wrapper.unmount();
    });
});
