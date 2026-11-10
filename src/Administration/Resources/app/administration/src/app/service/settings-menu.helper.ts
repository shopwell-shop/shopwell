/**
 * @sw-package framework
 */
import type { SettingsItem } from '../store/settings-item.store';
import type { DatabaseMenuNode, NavigationEntryLike } from '../component/structure/sw-admin-menu/merge-navigation.helper';

/** @private */
export type SettingsNavigationItem = Omit<SettingsItem, 'label'> & {
    label?: string | { label: string; translated: boolean };
    privilege?: string | null;
    menuPrivilege?: string | null;
    inheritedMenuPrivileges?: string[];
    groupTitle?: string | null;
    href?: string;
    position?: number;
};

type SettingsSection = { group: string; title: string | null; privileges: string[] };

function settingsSection(node: DatabaseMenuNode, inherited: SettingsSection): SettingsSection {
    return {
        group: node.type === 'group' ? node.id : node.groupName || node.id,
        title: node.groupTitle || node.title || inherited.title,
        privileges: node.privilege ? [...inherited.privileges, node.privilege] : inherited.privileges,
    };
}

function withSettingsSection(item: SettingsNavigationItem, section: SettingsSection): SettingsNavigationItem {
    return {
        ...item,
        group: section.group,
        groupTitle: section.title,
        inheritedMenuPrivileges: [...(item.inheritedMenuPrivileges ?? []), ...section.privileges],
    };
}

function settingsRoot(
    nodes: DatabaseMenuNode[],
    ancestors: string[] = [],
): { node: DatabaseMenuNode; privileges: string[] } | undefined {
    for (const node of nodes) {
        const privileges = node.privilege ? [...ancestors, node.privilege] : ancestors;
        if (node.internalKey === 'sw-settings') return { node, privileges };
        const nested = settingsRoot(node.children ?? [], privileges);
        if (nested) return nested;
    }
    return undefined;
}

const isContainer = (node: DatabaseMenuNode) => node.type === 'folder' || node.type === 'group';

const routeOf = (item: SettingsNavigationItem) => (typeof item.to === 'string' ? item.to : item.to?.name);

function settingsTarget(node: DatabaseMenuNode, source: Partial<SettingsNavigationItem>, entry: NavigationEntryLike) {
    const href =
        node.type === 'link' && /^https?:\/\//i.test(node.linkTarget ?? '') ? (node.linkTarget ?? undefined) : undefined;
    return {
        to: href ? undefined : (source.to ?? (entry.path || undefined)),
        href,
        label: node.title
            ? { label: node.title, translated: false }
            : (source.label ?? (typeof entry.label === 'string' ? entry.label : undefined)),
        icon: node.icon ?? source.icon ?? entry.icon ?? 'regular-link',
        privilege: source.privilege ?? entry.privilege,
        menuPrivilege: node.privilege,
    };
}

/** @private */
export function buildSettingsNavigation(
    declaredGroups: Record<string, SettingsNavigationItem[]>,
    tree: DatabaseMenuNode[],
    managedKeys: string[],
    navigation: NavigationEntryLike[] = [],
): Record<string, SettingsNavigationItem[]> {
    const declared = Object.values(declaredGroups).flat();
    const managed = new Set(managedKeys);
    const managedRoutes = new Set(
        navigation
            .filter((entry) => managed.has(entry.id ?? '') || managed.has(entry.path ?? ''))
            .map((entry) => entry.path),
    );
    const result = Object.create(null) as Record<string, SettingsNavigationItem[]>;
    const matched = new Set<SettingsNavigationItem>();
    const legacyGroups = new Map<string, SettingsSection>();
    const settings = settingsRoot(tree);
    let position = 0;

    const append = (group: string, item: SettingsNavigationItem) => {
        result[group] ??= [];
        result[group].push(item);
    };

    const visit = (
        nodes: DatabaseMenuNode[],
        inherited = { group: 'general', title: null as string | null, privileges: [] as string[] },
    ) => {
        nodes.forEach((node) => {
            const key = node.routeKey ?? node.internalKey ?? node.id;
            const group = node.groupName || inherited.group;
            const title = node.groupTitle || inherited.title;

            if (isContainer(node)) {
                const section = settingsSection(node, inherited);
                // Legacy module declarations join a configured section without merging independent group nodes.
                if (node.type === 'group' && node.groupName && !legacyGroups.has(node.groupName)) {
                    legacyGroups.set(node.groupName, section);
                }
                visit(node.children ?? [], section);
                return;
            }

            const entry = navigation.find((item) => item.id === key || item.path === key);
            const source = declared.find(
                (item) => item.id === key || routeOf(item) === key || (!!entry?.path && routeOf(item) === entry.path),
            );
            const target = settingsTarget(node, source ?? {}, entry ?? {});
            if (!target.to && !target.href) return;
            if (source) {
                matched.add(source);
            }

            append(group, {
                ...source,
                id: node.id,
                name: node.id,
                group,
                groupTitle: title,
                ...target,
                inheritedMenuPrivileges: inherited.privileges,
                position: position++,
            });
        });
    };

    visit(settings?.node.children ?? [], { group: 'general', title: null, privileges: settings?.privileges ?? [] });
    declared.forEach((item) => {
        if (
            matched.has(item) ||
            managed.has(item.id ?? '') ||
            managed.has(routeOf(item) ?? '') ||
            managedRoutes.has(routeOf(item))
        ) {
            return;
        }
        const group = typeof item.group === 'function' ? item.group() : item.group;
        const section = legacyGroups.get(group);
        append(section?.group ?? group, section ? withSettingsSection(item, section) : item);
    });

    return result;
}
