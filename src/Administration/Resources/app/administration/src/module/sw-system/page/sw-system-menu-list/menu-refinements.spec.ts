/** @sw-package fundamentals@framework */
import { createDraft, validateDraft, buildPayload } from './menu-draft';
import { createMenuPresentation } from './menu-presentation';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';

describe('menu refinements', () => {
    it('requires roles in specified visibility mode and clears bindings only for all-users mode', () => {
        const draft = createDraft({ title: 'Tools', type: 'group', visibilityMode: 'roles' });
        expect(validateDraft(draft)).toBe('sw-system.menu.notification.rolesRequired');
        draft.roleIds = ['role'];
        expect(validateDraft(draft)).toBeNull();
        draft.visibilityMode = 'all';
        expect(buildPayload(draft, 'en').roles).toEqual([]);
    });
    it('offers independent groups and builds targets from declarations, not customized placement', () => {
        const presentation = createMenuPresentation({
            items: [],
            tree: [],
            draft: null,
            languageId: 'en',
            translate: (key) => key,
            routeAllowlist: [
                { internalKey: 'catalog', title: 'Catalog', type: 'folder', parent: null, privilege: null },
                { internalKey: 'product', title: 'Products', type: 'menu', parent: 'catalog', privilege: 'product.viewer' },
            ],
        });
        expect(presentation.typeOptions.map((option) => option.value)).toEqual([
            'menu',
            'folder',
            'group',
            'link',
        ]);
        expect(presentation.routeOptions).toMatchObject([
            { value: 'catalog', selectable: false, children: [{ value: 'product', label: 'Products' }] },
        ]);
    });

    it('keeps declaration identity when changing its type or target page', () => {
        const draft = createDraft({ title: 'Tools', internalKey: 'declared-group', routeKey: 'product', type: 'menu' });
        expect(buildPayload(draft, 'en')).toMatchObject({ internalKey: 'declared-group', routeKey: 'product' });
    });
    it.each([
        ['en', 'General'],
        ['zh', '常规'],
    ])('resolves migrated group fallback titles in %s and preserves custom titles', (languageId, title) => {
        const group: MenuTreeNode = {
            id: 'group',
            type: 'group',
            internalKey: 'group',
            groupName: null,
            children: [],
            translations: { [languageId]: { title: 'sw-system.menu.groups.general', groupTitle: null } },
            parentId: null,
            afterMenuItemId: null,
            title: null,
            icon: null,
            color: null,
            linkTarget: null,
            privilege: null,
            active: true,
            sysLocked: false,
            archived: false,
            configOverridden: false,
            pluginId: null,
            pluginName: null,
            roleIds: [],
        };
        const context = {
            items: [],
            tree: [group],
            draft: null,
            languageId,
            routeAllowlist: [],
            translate: (key: string) => (key === 'sw-system.menu.groups.general' ? title : key),
        };
        const presentation = createMenuPresentation(context);
        expect(presentation.tree[0].label).toBe(title);
        expect(presentation.parentOptions[1].label).toBe(title);
        group.translations[languageId].title = 'My tools';
        expect(createMenuPresentation(context).tree[0].label).toBe('My tools');
    });
});
