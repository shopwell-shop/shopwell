/**
 * @sw-package fundamentals@framework
 */
import type { AdminMenuItem, MenuTreeNode, RouteAllowlistEntry } from 'src/app/store/admin-menu-config.store';
import type { MenuDraft } from './menu-draft';
import type { TreeSelectOption } from 'src/app/component/form/sw-tree-select/tree-options';
import { collectDescendantIds } from './tree-filter';

type RenderNode = MenuTreeNode & { label: string; groupLabel: string | null; children: RenderNode[] };
type Option = { value: string; label: string };
type MenuPresentationContext = {
    items: AdminMenuItem[];
    tree: MenuTreeNode[];
    routeAllowlist: RouteAllowlistEntry[];
    draft: MenuDraft | null;
    languageId: string;
    translate: (key: string) => string;
};

function groupLabelOf(
    context: MenuPresentationContext,
    groupName: string | null,
    item: Partial<AdminMenuItem> = {},
): string | null {
    if (!groupName) {
        return null;
    }

    const stored = item.translations?.[context.languageId]?.groupTitle;

    if (stored) {
        return context.translate(stored);
    }

    const key = `sw-system.menu.groups.${groupName}`;

    const label = context.translate(key);
    return label === key ? groupName : label;
}

function labelOf(context: MenuPresentationContext, item: AdminMenuItem): string {
    const stored = item.translations?.[context.languageId]?.title;

    if (stored) {
        return context.translate(stored);
    }

    if (item.title) {
        return context.translate(item.title);
    }

    const declared = context.routeAllowlist.find((route) => route.internalKey === item.internalKey);

    if (declared?.title) {
        return context.translate(declared.title);
    }

    return item.internalKey || context.translate('sw-system.menu.labelUntitled');
}

function toRenderNodes(context: MenuPresentationContext, nodes: MenuTreeNode[]): RenderNode[] {
    return nodes.map((node) => {
        return {
            ...node,
            label: labelOf(context, node),
            groupLabel: groupLabelOf(context, node.groupName, node),
            children: toRenderNodes(context, node.children),
        };
    });
}

function parentOptions(context: MenuPresentationContext, tree: RenderNode[]): TreeSelectOption[] {
    const blocked = new Set(context.draft?.id ? [context.draft.id, ...collectDescendantIds(tree, context.draft.id)] : []);
    const walk = (nodes: RenderNode[]): TreeSelectOption[] =>
        nodes.flatMap((node) => {
            if (blocked.has(node.id)) return [];
            const children = walk(node.children);
            if (!['folder', 'group'].includes(node.type) && node.internalKey !== 'sw-settings') return children;
            return [{ value: node.id, label: node.label, children }];
        });
    return [{ value: '', label: context.translate('sw-system.menu.parentRoot') }, ...walk(tree)];
}

function routeOptions(context: MenuPresentationContext): TreeSelectOption[] {
    const byKey = new Map(
        context.routeAllowlist.map((route) => [
            route.internalKey,
            {
                value: route.internalKey,
                label: context.translate(route.title),
                description: route.type === 'menu' ? route.internalKey : undefined,
                selectable: route.type === 'menu',
                children: [] as TreeSelectOption[],
            },
        ]),
    );
    const roots: TreeSelectOption[] = [];
    context.routeAllowlist.forEach((route) => {
        const option = byKey.get(route.internalKey);
        if (!option) return;
        const parent = route.parent ? byKey.get(route.parent) : undefined;
        if (parent && parent !== option) parent.children.push(option);
        else roots.push(option);
    });
    return roots;
}

/** @private */
export function createMenuPresentation(context: MenuPresentationContext) {
    const tree = toRenderNodes(context, context.tree);
    return {
        tree,
        typeOptions: [
            'menu',
            'folder',
            'group',
            'link',
            ...(context.draft?.type === 'hidden' ? ['hidden'] : []),
        ].map((value) => ({
            value,
            label: context.translate(`sw-system.menu.types.${value}`),
        })),
        routeOptions: routeOptions(context),
        parentOptions: parentOptions(context, tree),
    };
}

/** @private */
export function createPrivilegeOptions(
    mappings: { key: string | null; roles: Record<string, unknown> }[],
    translate: (key: string) => string,
): Option[] {
    const options = [{ value: '', label: translate('sw-system.menu.privilegeNone') }];

    mappings.forEach((mapping) => {
        if (mapping.key === null) {
            return;
        }

        Object.keys(mapping.roles).forEach((role) => {
            const value = `${mapping.key}.${role}`;

            options.push({
                value,
                label: `${translate(`sw-privileges.permissions.${mapping.key}.label`)} · ${translate(`sw-privileges.roles.${role}`)}`,
            });
        });
    });

    return options;
}
