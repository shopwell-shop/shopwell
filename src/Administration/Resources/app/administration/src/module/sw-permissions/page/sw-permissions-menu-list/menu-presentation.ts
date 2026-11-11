/**
 * @sw-package fundamentals@framework
 */
import type { AdminMenuItem, MenuTreeNode, RouteAllowlistEntry } from 'src/app/store/admin-menu-config.store';
import type { MenuDraft } from './menu-draft';
import type { TreeSelectOption } from 'src/app/component/form/sw-tree-select/tree-options';
import { collectDescendantIds } from './tree-filter';

type RenderNode = MenuTreeNode & { label: string; groupLabel: string | null; children: RenderNode[] };
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

    const key = `sw-permissions.menu.groups.${groupName}`;

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

    return item.internalKey || context.translate('sw-permissions.menu.labelUntitled');
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
            const selectable = ['folder', 'group'].includes(node.type) || node.internalKey === 'sw-settings';
            if (!selectable && children.length === 0) return [];
            return [{ value: node.id, label: node.label, selectable, children }];
        });
    return walk(tree);
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
            label: context.translate(`sw-permissions.menu.types.${value}`),
        })),
        routeOptions: routeOptions(context),
        parentOptions: parentOptions(context, tree),
    };
}
