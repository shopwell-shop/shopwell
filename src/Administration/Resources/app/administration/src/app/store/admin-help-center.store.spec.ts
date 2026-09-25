import { createPinia, setActivePinia } from 'pinia';
import type { AdminHelpCenterStore } from './admin-help-center.store';

describe('admin-help-center', () => {
    let store: AdminHelpCenterStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopwell.Store.get('adminHelpCenter');
    });

    it('has initial state', () => {
        expect(store.showHelpSidebar).toBe(false);
        expect(store.showShortcutModal).toBe(false);
    });
});
