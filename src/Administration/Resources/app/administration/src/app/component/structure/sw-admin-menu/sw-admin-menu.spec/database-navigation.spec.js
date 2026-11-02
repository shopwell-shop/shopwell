/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
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
            { id: 'v1', internalKey: 'sw.first.top.level', title: 'Stored first', children: [] },
        ];
        await wrapper.vm.$nextTick();

        const merged = wrapper.vm.databaseNavigation;
        const storedFirst = merged.find((entry) => entry.id === 'sw.first.top.level');

        expect(storedFirst.label).toEqual({ label: 'Stored first', translated: true });
        // the stored chain decides the order, not the positions the modules declare
        expect(merged.findIndex((entry) => entry.id === 'sw.second.top.level')).toBeLessThan(
            merged.findIndex((entry) => entry.id === 'sw.first.top.level'),
        );
    });

    it('builds the sidebar tree out of the stored entries, children included', async () => {
        store.visibleTree = [
            {
                id: 'v1',
                internalKey: 'sw.second.top.level',
                title: 'Stored parent',
                children: [{ id: 'v2', internalKey: 'sw.second.level.first', title: 'Stored child', children: [] }],
            },
            { id: 'v3', internalKey: 'sw.first.top.level', title: 'Stored first', children: [] },
        ];
        await wrapper.vm.$nextTick();

        const roots = wrapper.vm.mainMenuEntries;

        // a stored root without the right parent shape takes its whole subtree with it,
        // leaving only the modules the store does not know in the sidebar
        expect(roots.map((entry) => entry.id)).toEqual([
            'sw.second.top.level',
            'sw.first.top.level',
            // declared but not stored: keeps rendering from its declaration
            'children.with.privilege',
        ]);
        expect(roots[0].children.map((entry) => entry.id)).toContain('sw.second.level.first');
    });

    it('keeps the declared navigation when the store carries nothing', () => {
        const merged = wrapper.vm.databaseNavigation;

        expect(merged.some((entry) => entry.id === 'sw.first.top.level')).toBe(true);
        expect(wrapper.vm.mainMenuEntries.length).toBeGreaterThan(0);
    });

    it('keeps the declared navigation when the stored navigation could not be loaded', async () => {
        store.visibleTree = [];
        store.isVisibleLoadFailed = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.mainMenuEntries.length).toBeGreaterThan(0);
    });
});
