/**
 * @sw-package fundamentals@framework
 */

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
    linkTarget: string;
    privilege: string | null;
    active: boolean;
    parentId: string | null;
    afterMenuItemId: string | null;
    roleIds: string[];
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
        linkTarget: '',
        privilege: null,
        active: true,
        parentId: null,
        afterMenuItemId: null,
        roleIds: [],
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
        linkTarget: draft.linkTarget,
        privilege: draft.privilege,
        active: draft.active,
        parentId: draft.parentId,
        afterMenuItemId: draft.afterMenuItemId,
        roleIds: [...draft.roleIds].sort(),
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
        linkTarget: draft.type === 'link' ? draft.linkTarget.trim() : null,
        privilege: draft.privilege || null,
        active: draft.active,
        roles: draft.roleIds.map((id) => ({ id })),
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
