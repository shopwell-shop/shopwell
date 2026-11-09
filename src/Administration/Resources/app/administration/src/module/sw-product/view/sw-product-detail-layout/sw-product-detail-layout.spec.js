import { shallowMount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */
describe('sw-product-detail-layout', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('passes product loading state to the Experience Studio card', async () => {
        Shopwell.Store.get('swProductDetail').loading.product = true;
        wrapper = shallowMount(await wrapTestComponent('sw-product-detail-layout', { sync: true }), {
            global: {
                stubs: {
                    'sw-product-layout-assignment': {
                        name: 'sw-product-layout-assignment',
                        props: ['isLoading'],
                        template: '<div />',
                    },
                },
            },
        });

        expect(wrapper.getComponent({ name: 'sw-product-layout-assignment' }).props('isLoading')).toBe(true);
    });
});
