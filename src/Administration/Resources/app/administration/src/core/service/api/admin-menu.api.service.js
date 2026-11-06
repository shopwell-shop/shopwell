/**
 * @sw-package fundamentals@framework
 */

/**
 * The transport of the database driven navigation.
 *
 * Creating, updating and deleting menu items is plain entity writing and goes through the
 * `admin_menu_item` repository; only the actions where a single row is not enough - reading
 * the management tree, moving an item with its ordering chain, the route allowlist - are
 * served by the `_action/admin-menu` endpoints this service wraps.
 */
class AdminMenuApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'adminMenuApiService';
    }

    /**
     * Every stored menu item, also the inactive, archived and therefore currently hidden
     * ones: the management tree has to show what an administrator can repair.
     *
     * @returns {Promise<Array>}
     */
    getTree() {
        const headers = this.getHeaders();

        return this.httpClient.get('/_action/admin-menu/tree', { headers }).then((response) => {
            return response.data.items ?? [];
        });
    }

    /**
     * The navigation tree the current user is allowed to see.
     *
     * Different from {@link getTree}: inactive, archived, hidden and role-restricted entries
     * are already dropped by the server, and entries the database does not know yet come back
     * as synthesized nodes, so the caller never has to merge declarations itself.
     *
     * @returns {Promise<Array>}
     */
    getVisibleTree() {
        const headers = this.getHeaders();

        return this.httpClient.get('/_action/admin-menu/visible', { headers }).then((response) => {
            return response.data.menu ?? [];
        });
    }

    /**
     * The internal keys a menu item may point at, declared by the core and plugin providers.
     *
     * @returns {Promise<Array>}
     */
    getRouteAllowlist() {
        const headers = this.getHeaders();

        return this.httpClient.get('/_action/admin-menu/route-whitelist', { headers }).then((response) => {
            return response.data.routes ?? [];
        });
    }

    /**
     * Writes a new item through the generated DAL route and returns its id: the caller has
     * to position it afterwards, and positioning is a move of the stored row.
     *
     * `_response=detail` is what makes the id readable - the plain write answers with an
     * empty body and a `Location` header only.
     *
     * @param {object} payload
     * @returns {Promise<string>}
     */
    createItem(payload) {
        const headers = this.getHeaders();

        return this.httpClient.post('/admin-menu-item?_response=detail', payload, { headers }).then((response) => {
            return response.data.data.id;
        });
    }

    /**
     * @param {string} id
     * @param {object} payload
     * @returns {Promise<string>}
     */
    updateItem(id, payload) {
        const headers = this.getHeaders();

        return this.httpClient.patch(`/admin-menu-item/${id}`, payload, { headers }).then(() => {
            return id;
        });
    }

    /**
     * @param {string} id
     */
    deleteItem(id) {
        const headers = this.getHeaders();

        return this.httpClient.delete(`/admin-menu-item/${id}`, { headers });
    }

    /**
     * Moves an item below `parentId` and right after `afterMenuItemId`. The server repairs
     * the ordering chain of both affected levels, so a single call is the whole move.
     *
     * @param {{ id: string, parentId: string|null, afterMenuItemId: string|null }} move
     */
    move({ id, parentId = null, afterMenuItemId = null }) {
        const headers = this.getHeaders();

        return this.httpClient.post('/_action/admin-menu/move', { id, parentId, afterMenuItemId }, { headers });
    }

    /**
     * Replaces the roles a menu item is bound to. An empty list makes the item visible to
     * every role.
     *
     * @param {{ id: string, roleIds: Array<string> }} assignment
     */
    assignRoles({ id, roleIds }) {
        const headers = this.getHeaders();

        return this.httpClient.post('/_action/admin-menu/assign-roles', { id, roleIds }, { headers });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
            'sw-language-id': Shopwell.Context.api.languageId,
        };
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default AdminMenuApiService;
