/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import { nextTick } from 'vue';
import createWrapper, { registerAdminModules } from './create-wrapper';

/**
 * The sidebar renders the stored navigation once the `visible` request has answered, and keeps
 * rendering the module declarations until then (and whenever the request fails).
 */
describe('src/app/component/structure/sw-admin-menu (database driven navigation)', () => {
    let wrapper;
    let store;

    beforeAll(() => {
        Shopwell.Store.get('session').currentLocale = 'en-GB';
        Shopwell.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();
    });

    beforeEach(async () => {
        // Fixes the v-bind false error for transition "persisted"; merged so the
        // globally registered meteor components stay resolvable
        config.global.stubs = {
            ...config.global.stubs,
            transition: false,
        };

        // the fixture declares a level-4 entry on purpose, which the menu reports and drops
        jest.spyOn(Shopwell.Utils.debug, 'error').mockImplementation(() => true);

        store = Shopwell.Store.get('adminMenuConfig');
        store.visibleTree = [];
        store.isVisibleLoadFailed = false;
        // the request is covered by the store's own tests, this suite is about the rendering
        store.loadVisibleTree = jest.fn(() => Promise.resolve());

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('asks the store for the stored navigation once on creation', () => {
        expect(store.loadVisibleTree).toHaveBeenCalledTimes(1);
    });

    it('renders the stored order and wording over the declarations', async () => {
        store.visibleTree = [
            { id: 'v2', internalKey: 'sw.second.top.level', title: 'Stored second', children: [] },
            { id: 'v1', internalKey: 'children.with.privilege', title: 'Stored first', children: [] },
        ];
        await nextTick();

        const roots = wrapper.findAll('.sw-admin-menu__navigation-list > li');
        const labels = roots.map((root) => root.find('.sw-admin-menu__navigation-link-label').text());
        expect(labels.indexOf('Stored second')).toBeLessThan(labels.indexOf('Stored first'));
        expect(labels).toContain('Stored first');
    });

    it('builds the sidebar tree out of the stored entries, children included', async () => {
        store.visibleTree = [
            {
                id: 'v1',
                internalKey: 'sw.second.top.level',
                title: 'Stored parent',
                children: [{ id: 'v2', internalKey: 'sw.second.level.first', title: 'Stored child', children: [] }],
            },
            { id: 'v3', internalKey: 'children.with.privilege', title: 'Stored first', children: [] },
        ];
        await nextTick();

        const roots = wrapper.findAll('.sw-admin-menu__navigation-list > li');
        expect(roots.map((root) => root.classes().find((name) => name.startsWith('sw-admin-menu__item--')))).toEqual([
            'sw-admin-menu__item--sw.second.top.level',
            'sw-admin-menu__item--children.with.privilege',
        ]);
        await wrapper.get('.sw-second-top-level .sw-admin-menu__navigation-link').trigger('click');
        expect(wrapper.get('.sw-second-top-level').text()).toContain('Stored child');
    });

    it('keeps the declared navigation when the store carries nothing', () => {
        expect(wrapper.find('.sw-second-top-level').exists()).toBe(true);
        expect(wrapper.findAll('.sw-admin-menu__navigation-list > li').length).toBeGreaterThan(0);
    });

    it('keeps the declared navigation when the stored navigation could not be loaded', async () => {
        store.visibleTree = [];
        store.isVisibleLoadFailed = true;
        await nextTick();

        expect(wrapper.findAll('.sw-admin-menu__navigation-list > li').length).toBeGreaterThan(0);
    });
});
