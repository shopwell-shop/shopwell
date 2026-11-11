/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import useAdminLayout from 'src/app/composables/use-admin-layout';

describe('inline layout settings', () => {
    let wrapper: VueWrapper;

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

    it('emits a draft choice without applying or persisting it', async () => {
        wrapper = await createWrapper();
        await wrapper.find('input[value="mixed"]').setValue(true);
        expect(wrapper.emitted('update:value')).toEqual([['mixed']]);
        expect(useAdminLayout().layout.value).toBe('sidebar');
        expect(jest.mocked(Shopwell.Service('userConfigService')).upsert.mock.calls).toHaveLength(0);
        await wrapper.setProps({ value: 'mixed' });
        expect(wrapper.find<HTMLInputElement>('input[value="mixed"]').element.checked).toBe(true);
        expect(wrapper.find<HTMLInputElement>('input[value="sidebar"]').element.checked).toBe(false);
    });

    it('renders in the profile page without a modal or separate close action', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('fieldset').exists()).toBe(true);
        expect(wrapper.find('.close').exists()).toBe(false);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });
});
