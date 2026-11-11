/**
 * @sw-package fundamentals@framework
 */

import type { AdminMenuItem, MenuTreeNode } from 'src/app/store/admin-menu-config.store';

/** @private */
export type MenuPageState = {
    expandedIds: string[];
    searchTerm: string;
    selectedId: string | null;
    languageId: string;
    languages: Entity<'language'>[];
    showDeleteModal: boolean;
    showUnsavedModal: boolean;
    pendingChange: (() => void) | null;
    pendingCancel: (() => void) | null;
    draft: MenuDraft | null;
    draftBaseline: string | null;
    deleteTarget: MenuTreeNode | null;
    isSaving: boolean;
    isDeleting: boolean;
    isMoving: boolean;
    fieldErrors: Record<string, { code: string; detail: string }>;
};

/**
 * The editable state of one menu item. The store is the source of truth for what is stored,
 * the draft for what the form shows, and the two only meet on save.
 *
 * @private
 */
export type MenuDraft = {
    id: string | null;
    title: string;
    groupName: string;
    groupTitle: string;
    icon: string;
    type: string;
    internalKey: string | null;
    routeKey: string;
    linkTarget: string;
    privilege: string | null;
    active: boolean;
    parentId: string | null;
    afterMenuItemId: string | null;
};

/**
 * The item as the store carries it, reduced to the two fields a placement is made of.
 *
 * @private
 */
export type PlacedItem = {
    parentId: string | null;
    afterMenuItemId: string | null;
};

/**
 * Creates the draft of a menu item.
 *
 * @private
 */
export function createDraft(overrides: Partial<MenuDraft> = {}): MenuDraft {
    return {
        id: null,
        title: '',
        groupName: '',
        groupTitle: '',
        icon: '',
        type: 'menu',
        internalKey: null,
        routeKey: overrides.type === 'menu' || !overrides.type ? (overrides.internalKey ?? '') : '',
        linkTarget: '',
        privilege: null,
        active: true,
        parentId: null,
        afterMenuItemId: null,
        ...overrides,
    };
}

/**
 * The comparable shape of a draft. Two drafts describe the same edit when their fingerprints
 * are equal, which is what separates an unsaved change from a language switch that only
 * re-reads the stored state.
 *
 * @private
 */
export function fingerprintDraft(draft: MenuDraft | null): string | null {
    if (!draft) {
        return null;
    }

    return JSON.stringify({
        id: draft.id,
        title: draft.title.trim(),
        groupName: draft.groupName.trim(),
        groupTitle: draft.groupTitle.trim(),
        icon: draft.icon,
        type: draft.type,
        internalKey: draft.internalKey,
        routeKey: draft.routeKey,
        linkTarget: draft.linkTarget,
        privilege: draft.privilege,
        active: draft.active,
        parentId: draft.parentId,
        afterMenuItemId: draft.afterMenuItemId,
    });
}

/**
 * The write payload of a draft. The labels are translated fields, so they are written for the
 * language the page shows instead of for the language the client happens to run in.
 *
 * @private
 */
export function buildPayload(draft: MenuDraft, languageId: string) {
    const translation = {
        languageId,
        title: draft.title.trim(),
        groupTitle: draft.groupTitle.trim() || null,
    };

    return {
        id: draft.id ?? undefined,
        translations: [translation],
        groupName: draft.groupName.trim() || null,
        icon: draft.icon || null,
        type: draft.type,
        internalKey: draft.internalKey || null,
        routeKey: draft.type === 'menu' ? draft.routeKey || null : null,
        linkTarget: draft.type === 'link' ? draft.linkTarget.trim() : null,
        active: draft.active,
    };
}

/**
 * Whether saving the draft has to move the item as well. A new item is always placed
 * explicitly, an existing one only when its level or its predecessor changed - a plain
 * rename must not trigger a second write.
 *
 * @private
 */
export function needsMove(draft: MenuDraft, storedItem: PlacedItem | null | undefined, parentId: string | null): boolean {
    if (draft.id === null) {
        return true;
    }

    return storedItem?.parentId !== parentId || storedItem?.afterMenuItemId !== draft.afterMenuItemId;
}

/** @private */
export function createPageState(languageId: string): MenuPageState {
    return {
        expandedIds: [],
        searchTerm: '',
        selectedId: null,
        languageId: languageId,
        languages: [],
        showDeleteModal: false,
        showUnsavedModal: false,
        pendingChange: null,
        pendingCancel: null,
        isMoving: false,
        fieldErrors: {},
        draft: null,
        draftBaseline: null,
        deleteTarget: null,
        isSaving: false,
        isDeleting: false,
    };
}

/** @private */
export function validateDraft(draft: MenuDraft | null): string | null {
    const prefix = 'sw-permissions.menu.notification.';
    if (!draft?.title.trim()) return `${prefix}titleRequired`;
    if (draft.type === 'menu' && !draft.routeKey) return `${prefix}targetRequired`;
    if (draft.type === 'link' && !/^https?:\/\//i.test(draft.linkTarget.trim())) return `${prefix}linkRequired`;
    return null;
}

/** @private */
export function draftFromItem(item: AdminMenuItem & { label: string }, languageId: string): MenuDraft {
    const stored = item.translations?.[languageId] ?? {};

    return createDraft({
        ...item,
        title: item.label,
        routeKey: item.type === 'menu' ? (item.routeKey ?? item.internalKey ?? '') : '',
        icon: item.icon ?? '',
        linkTarget: item.linkTarget ?? '',
        groupName: item.groupName ?? '',
        groupTitle: stored.groupTitle ?? '',
    });
}

/** @private */
export function changeDraftType(draft: MenuDraft, type: string): MenuDraft {
    return {
        ...draft,
        type,
        routeKey: type === 'menu' ? draft.routeKey : '',
        linkTarget: type === 'link' ? draft.linkTarget : '',
    };
}
