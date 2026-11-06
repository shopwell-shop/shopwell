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
