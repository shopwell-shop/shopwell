import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from './sidebar.init';

describe('src/app/init/sidebar.init', () => {
    beforeAll(() => {
        // Execute the initializeSidebar function
        initializeSidebar();
    });

    beforeEach(() => {
        // Reset the sidebar store
        Shopwell.Store.get('sidebar').sidebars = [];
        Shopwell.Store.get('sidebar').closingSidebar = null;

        Shopwell.Store.get('extensions').extensionsState = {};
        Shopwell.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('should handle uiSidebarAdd', async () => {
        // Check that sidebar store is empty
        expect(Shopwell.Store.get('sidebar').sidebars).toEqual([]);

        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Shopwell.Store.get('sidebar').sidebars).toHaveLength(1);
        expect(Shopwell.Store.get('sidebar').sidebars[0]).toEqual({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
            active: false,
            baseUrl: '',
        });
    });

    it('should handle uiSidebarClose', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Shopwell.Store.get('sidebar').sidebars).toHaveLength(1);

        // Check that sidebar is not active
        expect(Shopwell.Store.get('sidebar').sidebars[0].active).toBe(false);

        // Open the sidebar
        Shopwell.Store.get('sidebar').sidebars[0].active = true;

        jest.useFakeTimers();

        // Close the sidebar
        await ui.sidebar.close({
            locationId: 'test-sidebar',
        });

        // The close plays the closing animation before deactivating
        expect(Shopwell.Store.get('sidebar').closingSidebar).toBe('test-sidebar');
        jest.advanceTimersByTime(400);
        jest.useRealTimers();

        // Check that sidebar is not active
        expect(Shopwell.Store.get('sidebar').sidebars[0].active).toBe(false);
    });

    it('should handle uiSidebarSetActive', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Shopwell.Store.get('sidebar').sidebars).toHaveLength(1);

        // Check that sidebar is not active
        expect(Shopwell.Store.get('sidebar').sidebars[0].active).toBe(false);

        // Activate the sidebar
        await ui.sidebar.setActive({
            locationId: 'test-sidebar',
        });

        // Check that sidebar is not active
        expect(Shopwell.Store.get('sidebar').sidebars[0].active).toBe(true);
    });

    it('should handle uiSidebarRemove', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Shopwell.Store.get('sidebar').sidebars).toHaveLength(1);

        // Remove the sidebar
        await ui.sidebar.remove({
            locationId: 'test-sidebar',
        });

        // Check that sidebar store is empty
        expect(Shopwell.Store.get('sidebar').sidebars).toEqual([]);
    });
});
