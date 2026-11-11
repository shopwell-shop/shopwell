/**
 * @sw-package fundamentals@framework
 */
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import AdminMenuApiService from './admin-menu.api.service';

describe('AdminMenuApiService', () => {
    it('reads the server-filtered navigation tree', async () => {
        const client = createHTTPClient(Shopwell.Context.api);
        const adapter = new MockAdapter(client);
        const service = new AdminMenuApiService(client, createLoginService(client, Shopwell.Context.api));
        const menu = [{ id: 'orders', label: 'Orders', children: [] }];
        adapter.onGet('/_action/admin-menu/visible').reply(200, { menu });

        await expect(service.getVisibleTree()).resolves.toEqual(menu);
        adapter.restore();
    });

    it('propagates navigation API failures instead of returning an empty tree', async () => {
        const client = createHTTPClient(Shopwell.Context.api);
        const adapter = new MockAdapter(client);
        const service = new AdminMenuApiService(client, createLoginService(client, Shopwell.Context.api));
        adapter
            .onGet('/_action/admin-menu/visible')
            .reply(403, { errors: [{ status: '403', code: 'FORBIDDEN', detail: 'Forbidden' }] });

        await expect(service.getVisibleTree()).rejects.toThrow();
        adapter.restore();
    });
});

describe('menu entity persistence', () => {
    const context = () => ({ ...Shopwell.Context.api, languageId: 'translation-language' });
    let entity: { id: string; roles: { id: string }[]; title?: string; groupTitle?: string | null };
    const repository = { create: jest.fn(), get: jest.fn(), save: jest.fn(), delete: jest.fn() };
    const roles = { get: jest.fn() };
    const factory = { create: (name: string) => (name === 'acl_role' ? roles : repository) };

    beforeEach(() => {
        jest.clearAllMocks();
        entity = { id: 'menu-id', roles: [{ id: 'old-role' }] };
        repository.create.mockReturnValue(entity);
        repository.get.mockResolvedValue(entity);
        repository.save.mockResolvedValue(undefined);
        roles.get.mockImplementation((id: string) => Promise.resolve({ id }));
    });

    it('writes structural fields and the selected language without loading roles', async () => {
        const client = createHTTPClient(Shopwell.Context.api);
        const service = new AdminMenuApiService(client, createLoginService(client, Shopwell.Context.api));
        jest.spyOn(service, 'repositories').mockReturnValue(
            factory as unknown as ReturnType<AdminMenuApiService['repositories']>,
        );
        await service.updateItem('menu-id', {
            translations: [{ languageId: 'translation-language', title: '菜单' }],
        });
        expect(entity.title).toBe('菜单');
        expect(repository.get).toHaveBeenCalledWith('menu-id', context());
        expect(roles.get).not.toHaveBeenCalled();
        expect(repository.save).toHaveBeenCalledWith(entity, context());
    });

    it('creates with a repository identity and deletes using the repository', async () => {
        const client = createHTTPClient(Shopwell.Context.api);
        const service = new AdminMenuApiService(client, createLoginService(client, Shopwell.Context.api));
        jest.spyOn(service, 'repositories').mockReturnValue(
            factory as unknown as ReturnType<AdminMenuApiService['repositories']>,
        );
        await expect(
            service.createItem({ type: 'folder', translations: [{ languageId: 'translation-language', title: '工具' }] }),
        ).resolves.toBe('menu-id');
        expect(repository.create).toHaveBeenCalledWith(context());
        await service.deleteItem('menu-id');
        expect(repository.delete).toHaveBeenCalledWith('menu-id', Shopwell.Context.api);
    });
    it('keeps the generated identity when a new group draft carries an undefined id', async () => {
        const client = createHTTPClient(Shopwell.Context.api);
        const service = new AdminMenuApiService(client, createLoginService(client, Shopwell.Context.api));
        jest.spyOn(service, 'repositories').mockReturnValue(
            factory as unknown as ReturnType<AdminMenuApiService['repositories']>,
        );
        entity.roles = [];
        await expect(
            service.createItem({
                id: undefined,
                type: 'group',
                translations: [{ languageId: 'translation-language', title: '工具' }],
            }),
        ).resolves.toBe('menu-id');
        expect(entity.id).toBe('menu-id');
    });
});
