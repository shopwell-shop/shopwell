/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import useAdminLayout from 'src/app/composables/use-admin-layout';

describe('desktop layout switching', () => {
    let wrapper: VueWrapper;

    beforeEach(() => {
        Shopwell.Store.get('session').currentUser = { id: 'layout-user' } as Entity<'user'>;
        (Shopwell.Service('userConfigService').search as jest.Mock).mockResolvedValue({ data: {} });
        (Shopwell.Service('userConfigService').upsert as jest.Mock).mockResolvedValue(undefined);
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('keeps an unsaved form mounted while changing all three layouts through settings', async () => {
        wrapper = mount(await wrapTestComponent('sw-desktop', { sync: true }), {
            global: {
                mocks: { $route: { name: 'test.index', meta: {}, path: '/test' } },
                provide: {
                    shopIdChangeService: { checkShopId: () => Promise.resolve(null) },
                    userActivityApiService: { increment: () => Promise.resolve() },
                    snackbarService: { addSnackbar: () => {} },
                },
                stubs: {
                    'sw-admin-menu': true,
                    'sw-admin-layout-settings': await wrapTestComponent('sw-admin-layout-settings', { sync: true }),
                    'sw-error-boundary': { template: '<div><slot /></div>' },
                    'router-view': { template: '<div><input aria-label="Unsaved draft"><sw-admin-layout-settings /></div>' },
                    'sw-sidebar-renderer': true,
                    'sw-app-shop-id-change-modal': true,
                    'sw-app-wrong-app-url-modal': true,
                    'sw-request-consent-modal': true,
                    'sw-ui-shell-update-2026-modal': true,
                    'sw-settings-services-grant-permissions-modal': true,
                    'sw-settings-usage-data-consent-modal-data-provider': true,
                },
            },
        });
        await flushPromises();
        const draft = wrapper.find<HTMLInputElement>('input[aria-label="Unsaved draft"]');
        await draft.setValue('keep this draft');

        for (const mode of ['top', 'mixed', 'sidebar']) {
            await wrapper.find(`input[value="${mode}"]`).setValue(true);
            await flushPromises();
            expect(useAdminLayout().layout.value).toBe(mode);
            expect(wrapper.classes().includes('sw-desktop--horizontal')).toBe(mode !== 'sidebar');
            expect(wrapper.find('input[aria-label="Unsaved draft"]').element).toBe(draft.element);
            expect(draft.element.value).toBe('keep this draft');
        }
    });
});
