/**
 * @sw-package framework
 */
import type { VueWrapper } from '@vue/test-utils';
import createWrapper from './create-wrapper';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

const entry = {
    id: 'catalogue',
    label: 'Catalogue',
    icon: 'regular-products',
    color: '#3b7f3f',
    level: 1,
    children: [{ id: 'products', label: 'Products', path: 'product.list', children: [] }],
};

describe('horizontal menu item', () => {
    afterEach(() => {
        useModuleIconColors().enabled.value = false;
    });

    it('opens a popup without rendering an inline subtree', async () => {
        const wrapper = await createWrapper({ props: { entry, presentation: 'topbar' } });
        const trigger = wrapper.get('button');
        await trigger.trigger('click');
        expect(wrapper.emitted('submenu-toggle')?.[0]?.[0]).toMatchObject({ entry, target: trigger.element });
        expect(wrapper.find('.sw-admin-menu__sub-navigation-list').exists()).toBe(false);
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await (wrapper as VueWrapper).setProps({ isExpanded: true });
        expect(trigger.attributes('aria-expanded')).toBe('true');
        wrapper.unmount();
    });

    it('uses existing active icons and module colors for a child route', async () => {
        useModuleIconColors().enabled.value = true;
        const wrapper = await createWrapper({
            props: { entry, presentation: 'topbar' },
            route: { name: 'product.list' },
        });
        expect(wrapper.get('.mt-icon').classes()).toContain('icon--solid-products');
        expect(wrapper.get('.mt-icon').attributes('style')).toContain('color: rgb(59, 127, 63)');
        expect(wrapper.classes()).toContain('is--module-colored');
        expect(wrapper.get('button').classes()).toContain('router-link-active');
        wrapper.unmount();
    });

    it('uses the supplied module target as a real link in mixed layout', async () => {
        const target = { name: 'product.list', query: { page: '3' } };
        const wrapper = await createWrapper({ props: { entry, presentation: 'topbar', navigationTarget: target } });
        expect(wrapper.find('button').exists()).toBe(false);
        expect(((wrapper.getComponent('.router-link') as VueWrapper).props() as { to: unknown }).to).toEqual(target);
        wrapper.unmount();
    });
});
