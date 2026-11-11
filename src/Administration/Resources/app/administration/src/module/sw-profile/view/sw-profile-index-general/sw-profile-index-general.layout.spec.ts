/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import useAdminLayout from 'src/app/composables/use-admin-layout';

describe('profile navigation layout preference', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('switches all three layouts from User interface while retaining an unsaved profile field', async () => {
        Shopwell.Store.get('session').currentUser = { id: 'profile-layout-user' } as Entity<'user'>;
        const configService = jest.mocked(Shopwell.Service('userConfigService'));
        configService.search.mockResolvedValue({ data: {} });
        configService.upsert.mockResolvedValue(undefined);
        await useAdminLayout().loadUserLayout();

        wrapper = mount(await wrapTestComponent('sw-profile-index-general', { sync: true }), {
            props: {
                user: { name: 'Original name' },
                languages: [],
                isUserLoading: false,
                languageId: 'language',
                isDisabled: false,
                userRepository: { schema: { entity: 'user' } },
                timezoneOptions: [],
            },
            global: {
                provide: {
                    acl: { can: () => true },
                    ssoSettingsService: { isSso: () => Promise.resolve({ isSso: true }) },
                },
                stubs: {
                    'sw-admin-layout-settings': await wrapTestComponent('sw-admin-layout-settings', { sync: true }),
                    'sw-single-select': true,
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-extension-component-section': true,
                },
            },
        });
        await flushPromises();
        const preference = wrapper.find('[position-identifier="sw-profile-index-general-user-interface"]');
        expect(preference.find('input[value="sidebar"]').exists()).toBe(true);

        const name = wrapper.find<HTMLInputElement>('input[name="sw-field--user-name"]');
        await name.setValue('Unsaved name');
        for (const mode of ['top', 'mixed', 'sidebar']) {
            await preference.find(`input[value="${mode}"]`).setValue(true);
            await flushPromises();
            expect(useAdminLayout().layout.value).toBe(mode);
            expect(name.element.value).toBe('Unsaved name');
            expect(wrapper.find('input[name="sw-field--user-name"]').element).toBe(name.element);
            expect(configService.upsert.mock.calls.at(-1)).toEqual([{ 'core.adminNavigationLayout': { layout: mode } }]);
        }
    });
});
