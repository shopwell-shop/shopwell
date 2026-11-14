/** @sw-package fundamentals@framework */
import { shallowMount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';
import type component from './index';

describe('permission grid assignment layout', () => {
    it('shows individual operations without parent and select-all rows in the assignment workspace', async () => {
        const privileges = new PrivilegesService();
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'product',
            parent: 'catalogues',
            roles: {
                viewer: { privileges: ['product:read'], dependencies: [] },
                editor: { privileges: ['product:update'], dependencies: ['product.viewer'] },
            },
        });
        const wrapper = shallowMount((await wrapTestComponent('sw-permissions-grid', { sync: true })) as typeof component, {
            props: { assignmentMode: true, role: { privileges: ['product.viewer', 'product.editor'] } },
            global: { provide: { privileges }, renderStubDefaultSlot: true },
        });

        expect(wrapper.find('.sw-permissions-grid__parent').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-grid__all').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-grid__entry_product').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-grid__dependency').exists()).toBe(true);

        await wrapper.setProps({ assignmentMode: false });
        expect(wrapper.find('.sw-permissions-grid__parent').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-grid__all').exists()).toBe(true);
        wrapper.unmount();
    });
});
