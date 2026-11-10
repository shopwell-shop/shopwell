/**
 * @sw-package fundamentals@framework
 */
import { createPinia, setActivePinia } from 'pinia';
import type { AdminMenuItem } from './admin-menu-config.store';

const api = {
    getTree: jest.fn(),
    getNavigation: jest.fn(),
    getRouteAllowlist: jest.fn(),
    createItem: jest.fn(),
    updateItem: jest.fn(),
    deleteItem: jest.fn(),
    move: jest.fn(),
};

function item(id: string, parentId: string | null): AdminMenuItem {
    return { id, parentId, groupName: 'regional', translations: {} } as AdminMenuItem;
}

describe('admin menu configuration', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(api).forEach((mock) => mock.mockReset());
        api.getTree.mockResolvedValue([]);
        api.getRouteAllowlist.mockResolvedValue([]);
        api.getNavigation.mockResolvedValue({ menu: [], managedKeys: ['sw-settings-tax'] });
        api.updateItem.mockImplementation((id: string) => Promise.resolve(id));
        jest.spyOn(Shopwell.Store.get('adminMenuConfig'), 'api').mockReturnValue(api);
    });

    it('loads the managed keys with the visible tree and clears them on failure', async () => {
        const store = Shopwell.Store.get('adminMenuConfig');
        await store.loadVisibleTree();
        expect(store.managedKeys).toEqual(['sw-settings-tax']);
        api.getNavigation.mockRejectedValueOnce(new Error('offline'));
        await store.loadVisibleTree(true);
        expect(store.managedKeys).toEqual([]);
        expect(store.isVisibleLoadFailed).toBe(true);
    });

    it('returns a created identity before refresh or group writes can fail', async () => {
        const store = Shopwell.Store.get('adminMenuConfig');
        api.createItem.mockResolvedValue('new-id');
        api.getTree.mockRejectedValueOnce(new Error('offline'));
        await expect(store.saveItem({ type: 'folder', groupName: 'regional' })).resolves.toBe('new-id');
        expect(api.getTree).not.toHaveBeenCalled();
        expect(api.updateItem).not.toHaveBeenCalled();
    });

    it('renames a group only within the same parent and language', async () => {
        const store = Shopwell.Store.get('adminMenuConfig');
        store.items = [item('tax', 'settings'), item('currency', 'settings'), item('other', null)];
        await store.saveGroupTitle({
            itemId: 'tax',
            groupName: 'regional',
            parentId: 'settings',
            languageId: 'zh',
            groupTitle: '区域设置',
        });
        expect(api.updateItem).toHaveBeenCalledTimes(1);
        expect(api.updateItem).toHaveBeenCalledWith('currency', {
            translations: [{ languageId: 'zh', groupTitle: '区域设置' }],
        });
    });

    it('exposes management load failures and supports retry', async () => {
        const store = Shopwell.Store.get('adminMenuConfig');
        api.getTree.mockRejectedValueOnce(new Error('offline'));
        await expect(store.load()).rejects.toThrow('offline');
        expect(store.isLoadFailed).toBe(true);
        await store.load();
        expect(store.isLoadFailed).toBe(false);
        expect(store.isLoading).toBe(false);
    });
});
