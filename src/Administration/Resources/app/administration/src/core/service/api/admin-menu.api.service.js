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
        return this.getNavigation().then((navigation) => navigation.menu);
    }

    getNavigation() {
        return this.httpClient.get('/_action/admin-menu/visible', { headers: this.getHeaders() }).then((response) => ({
            menu: response.data.menu ?? [],
            managedKeys: response.data.managedKeys ?? [],
        }));
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

    createItem(payload) {
        return this.writeItem(null, payload);
    }

    updateItem(id, payload) {
        return this.writeItem(id, payload);
    }

    deleteItem(id) {
        return this.repositories().create('admin_menu_item').delete(id, Shopwell.Context.api);
    }

    repositories() {
        return Shopwell.Service('repositoryFactory');
    }

    async writeItem(id, payload) {
        const { translations = [], roles, ...fields } = payload;
        const translation = translations[0];
        const context = { ...Shopwell.Context.api, languageId: translation?.languageId ?? Shopwell.Context.api.languageId };
        const repository = this.repositories().create('admin_menu_item');
        const criteria = new Shopwell.Data.Criteria();
        criteria.addAssociation('roles');
        const entity = id ? await repository.get(id, context, criteria) : repository.create(context);

        // The repository owns the identity, including the generated id of a new draft.
        delete fields.id;
        Object.assign(entity, fields);
        if (translation) {
            if ('title' in translation) entity.title = translation.title;
            if ('groupTitle' in translation) entity.groupTitle = translation.groupTitle;
        }
        if (roles) {
            const selected = new Set(roles.map((role) => role.id));
            const existing = new Set(entity.roles.map((role) => role.id));
            [...existing].filter((roleId) => !selected.has(roleId)).forEach((roleId) => entity.roles.remove(roleId));
            const roleRepository = this.repositories().create('acl_role');
            const additions = await Promise.all(
                [...selected].filter((roleId) => !existing.has(roleId)).map((roleId) => roleRepository.get(roleId, context)),
            );
            additions.forEach((role) => entity.roles.add(role));
        }

        await repository.save(entity, context);
        return entity.id;
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
