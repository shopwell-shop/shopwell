/**
 * @sw-package framework
 */
import useAdminLayout from './use-admin-layout';

const configKey = 'core.adminNavigationLayout';
const configService = jest.mocked(Shopwell.Service('userConfigService'));

function signIn(id: string): void {
    Shopwell.Store.get('session').currentUser = { id } as Entity<'user'>;
}

describe('administration layout preference', () => {
    beforeEach(async () => {
        localStorage.clear();
        configService.search.mockReset().mockResolvedValue({ data: {} });
        configService.upsert.mockReset().mockResolvedValue(undefined);
        signIn('layout-user');
        await useAdminLayout().loadUserLayout();
    });

    it('applies a choice immediately and saves it for the current account', async () => {
        const layout = useAdminLayout();
        const saving = layout.saveUserLayout('mixed');
        expect(layout.layout.value).toBe('mixed');
        await saving;
        expect(configService.upsert.mock.calls).toEqual([[{ [configKey]: { layout: 'mixed' } }]]);
        expect(localStorage.getItem('sw-admin-layout:layout-user')).toBe('mixed');
    });

    it('loads the server preference and normalizes invalid values', async () => {
        configService.search.mockResolvedValueOnce({ data: { [configKey]: { layout: 'top' } } });
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('top');
        configService.search.mockResolvedValueOnce({ data: { [configKey]: { layout: 'invalid' } } });
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('sidebar');
    });

    it('keeps the local choice on save failure so it can be retried', async () => {
        configService.upsert.mockRejectedValueOnce(new Error('offline'));
        await expect(useAdminLayout().saveUserLayout('top')).rejects.toThrow('offline');
        expect(useAdminLayout().layout.value).toBe('top');
        await useAdminLayout().saveUserLayout('top');
        expect(configService.upsert.mock.calls).toHaveLength(2);
    });

    it('isolates cached preferences across accounts and uses the cache on load failure', async () => {
        await useAdminLayout().saveUserLayout('mixed');
        signIn('other-user');
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('sidebar');
        signIn('layout-user');
        configService.search.mockResolvedValueOnce(undefined);
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('mixed');
    });

    it('ignores a previous account response arriving after an account switch', async () => {
        let resolvePrevious!: (value: Awaited<ReturnType<typeof configService.search>>) => void;
        configService.search.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolvePrevious = resolve;
                }),
        );
        const loading = useAdminLayout().loadUserLayout();
        signIn('other-user');
        await useAdminLayout().loadUserLayout();
        resolvePrevious({ data: { [configKey]: { layout: 'top' } } });
        await loading;
        expect(useAdminLayout().layout.value).toBe('sidebar');
    });

    it('does not let an older load overwrite a newly selected layout', async () => {
        let resolveLoading!: (value: Awaited<ReturnType<typeof configService.search>>) => void;
        configService.search.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveLoading = resolve;
                }),
        );
        const loading = useAdminLayout().loadUserLayout();
        await useAdminLayout().saveUserLayout('mixed');
        resolveLoading({ data: { [configKey]: { layout: 'sidebar' } } });
        await loading;
        expect(useAdminLayout().layout.value).toBe('mixed');
    });

    it('continues working when browser storage is unavailable', async () => {
        const get = jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        const set = jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        await expect(useAdminLayout().loadUserLayout()).resolves.toBeUndefined();
        await expect(useAdminLayout().saveUserLayout('top')).resolves.toBeUndefined();
        expect(useAdminLayout().layout.value).toBe('top');
        get.mockRestore();
        set.mockRestore();
    });
});
