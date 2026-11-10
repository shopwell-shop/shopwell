/**
 * @sw-package discovery
 */
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';
import ApiService from 'src/core/service/api.service';
import createLoginService from 'src/core/service/login.service';
import BlogApiService from './blog.api.service';

describe('Blog category move API', () => {
    let adapter: MockAdapter;
    let service: BlogApiService;

    beforeEach(() => {
        const client = createHTTPClient(Shopwell.Context.api);
        adapter = new MockAdapter(client);
        const loginService = createLoginService(client, Shopwell.Context.api);
        jest.spyOn(loginService, 'getToken').mockReturnValue('blog-token');
        service = new BlogApiService(new ApiService(client, loginService, 'blog/category'));
    });

    afterEach(() => {
        adapter.restore();
        jest.restoreAllMocks();
    });

    it.each([
        { parentId: 'parent-category', afterCategoryId: 'previous-category' },
        { parentId: null, afterCategoryId: null },
    ])('sends the parent and previous sibling with authentication: %j', async ({ parentId, afterCategoryId }) => {
        adapter.onPost('_action/blog/category/category-id/move').reply(200, { success: true });

        await expect(service.moveCategory('category-id', parentId, afterCategoryId)).resolves.toBeUndefined();

        expect(adapter.history.post).toHaveLength(1);
        expect(JSON.parse(adapter.history.post[0].data as string)).toEqual({ parentId, afterCategoryId });
        expect(adapter.history.post[0].headers).toEqual(
            expect.objectContaining({ Authorization: 'Bearer blog-token', 'Content-Type': 'application/json' }),
        );
    });

    it.each([403, 500])('propagates a failed category move (%s)', async (status) => {
        adapter.onPost('_action/blog/category/category-id/move').reply(status, { errors: [{ code: 'MOVE_FAILED' }] });

        await expect(service.moveCategory('category-id', null, null)).rejects.toMatchObject({ response: { status } });
    });
});
