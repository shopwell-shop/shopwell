/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import useAdminLayout from 'src/app/composables/use-admin-layout';

describe('inline layout settings', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-admin-layout-settings', { sync: true }));
    }

    beforeEach(async () => {
        Shopwell.Store.get('session').currentUser = { id: 'layout-reader' } as Entity<'user'>;
        (Shopwell.Service('userConfigService').search as jest.Mock).mockResolvedValue({ data: {} });
        (Shopwell.Service('userConfigService').upsert as jest.Mock).mockResolvedValue(undefined);
        await useAdminLayout().loadUserLayout();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('switches layout through the visible cards and marks the current choice', async () => {
        wrapper = await createWrapper();
        await wrapper.find('input[value="mixed"]').setValue(true);
        await flushPromises();
        expect(useAdminLayout().layout.value).toBe('mixed');
        expect(wrapper.find<HTMLInputElement>('input[value="mixed"]').element.checked).toBe(true);
        expect(wrapper.find<HTMLInputElement>('input[value="sidebar"]').element.checked).toBe(false);
    });

    it('keeps the selected layout on save failure and offers a working retry', async () => {
        (Shopwell.Service('userConfigService').upsert as jest.Mock).mockRejectedValueOnce(new Error('offline'));
        wrapper = await createWrapper();
        await wrapper.find('input[value="top"]').setValue(true);
        await flushPromises();
        expect(useAdminLayout().layout.value).toBe('top');
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        await wrapper.find('.sw-admin-layout-settings__retry').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('renders in the profile page without a modal or separate close action', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('fieldset').exists()).toBe(true);
        expect(wrapper.find('.close').exists()).toBe(false);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });
});
