/**
 * @sw-package framework
 */
import useAdminLayout, { USER_NAVIGATION_LAYOUT_CONFIG_KEY } from './use-admin-layout';

const configKey = USER_NAVIGATION_LAYOUT_CONFIG_KEY;
const configService = jest.mocked(Shopwell.Service('userConfigService'));

function signIn(id: string): void {
    Shopwell.Store.get('session').currentUser = { id } as Entity<'user'>;
}

describe('administration layout preference', () => {
    beforeEach(async () => {
        configService.search.mockReset().mockResolvedValue({ data: {} });
        configService.upsert.mockReset().mockResolvedValue(undefined);
        signIn('layout-user');
        await useAdminLayout().loadUserLayout();
    });

    it('shares the same preference state across consumers', () => {
        expect(useAdminLayout()).toBe(useAdminLayout());
    });

    it('applies a choice after it is saved for the current account', async () => {
        const layout = useAdminLayout();
        const saving = layout.saveUserLayout('mixed');
        expect(layout.layout.value).toBe('sidebar');
        await saving;
        expect(layout.layout.value).toBe('mixed');
        expect(configService.upsert.mock.calls).toEqual([[{ [configKey]: { layout: 'mixed' } }]]);
    });

    it('loads the server preference and normalizes invalid values', async () => {
        configService.search.mockResolvedValueOnce({ data: { [configKey]: { layout: 'top' } } });
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('top');
        configService.search.mockResolvedValueOnce({ data: { [configKey]: { layout: 'invalid' } } });
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('sidebar');
    });

    it('keeps the saved layout on failure so the draft can be retried', async () => {
        configService.upsert.mockRejectedValueOnce(new Error('offline'));
        await expect(useAdminLayout().saveUserLayout('top')).rejects.toThrow('offline');
        expect(useAdminLayout().layout.value).toBe('sidebar');
        await useAdminLayout().saveUserLayout('top');
        expect(configService.upsert.mock.calls).toHaveLength(2);
    });

    it('keeps the current account preference when loading fails', async () => {
        await useAdminLayout().saveUserLayout('mixed');
        configService.search.mockResolvedValueOnce(undefined);
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('mixed');
    });

    it('resets the previous account preference when switching accounts', async () => {
        await useAdminLayout().saveUserLayout('mixed');
        signIn('other-user');
        configService.search.mockResolvedValueOnce(undefined);
        await useAdminLayout().loadUserLayout();
        expect(useAdminLayout().layout.value).toBe('sidebar');
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

    it('does not apply a save response after switching accounts', async () => {
        let completeSave!: () => void;
        configService.upsert.mockImplementationOnce(
            () =>
                new Promise<void>((resolve) => {
                    completeSave = resolve;
                }),
        );
        const saving = useAdminLayout().saveUserLayout('top');
        signIn('other-user');
        await useAdminLayout().loadUserLayout();
        completeSave();
        await saving;
        expect(useAdminLayout().layout.value).toBe('sidebar');
    });
});
