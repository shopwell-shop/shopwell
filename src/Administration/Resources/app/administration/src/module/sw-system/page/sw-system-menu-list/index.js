/**
 * @sw-package fundamentals@framework
 */
import template from './sw-system-menu-list.html.twig';
import './sw-system-menu-list.scss';

const { Data, Mixin } = Shopwell;
const { Criteria } = Data;

const TYPE_MENU = 'menu';
const TYPE_FOLDER = 'folder';
const TYPE_LINK = 'link';
const TYPE_HIDDEN = 'hidden';

/**
 * Creates the draft of a menu item. The draft is the only editable state of the page: the
 * store is the source of truth for what is stored, the draft for what the form shows, and
 * the two only meet on save.
 */
function createDraft(overrides = {}) {
    return {
        id: null,
        title: '',
        icon: '',
        type: TYPE_MENU,
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
 * The management page of the database driven navigation.
 *
 * The stored navigation is a tree; the form edits one item at a time. Both are always on
 * screen - the tree decides which item the form shows, and a drop in the tree is a move
 * request, not a local reorder: `AdminMenuConfigService::move()` repairs the ordering chain
 * of both affected levels, so a reorder must never be computed on the client as well.
 *
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'privileges',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('translate-with-fallback')],

    data() {
        return {
            selectedId: null,
            draft: null,
            snapshot: null,
            isSaving: false,
            isDeleting: false,
            showDeleteModal: false,
            showDiscardModal: false,
            pendingSelectionId: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        store() {
            return Shopwell.Store.get('adminMenuConfig');
        },

        items() {
            return this.store.items;
        },

        tree() {
            return this.store.tree;
        },

        routeAllowlist() {
            return this.store.routeAllowlist;
        },

        isLoading() {
            return this.store.isLoading && !this.store.isLoaded;
        },

        canEdit() {
            return this.acl.can('admin_menu.editor');
        },

        /**
         * Core menu items store a snippet key instead of a literal title. The field has to show
         * the stored value - editing anything else would write the translation back as the key -
         * so the field explains itself instead of the stored key being "fixed" silently.
         */
        titleIsSnippetKey() {
            return !!this.draft.title && this.$te(this.draft.title);
        },

        canCreate() {
            return this.acl.can('admin_menu.creator');
        },

        canDelete() {
            return this.acl.can('admin_menu.deleter');
        },

        aclRoleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleCriteria() {
            return new Criteria(1, 500);
        },

        isDirty() {
            if (this.draft === null) {
                return false;
            }

            return JSON.stringify(this.draft) !== this.snapshot;
        },

        selectedItem() {
            return this.selectedId ? this.store.itemById(this.selectedId) : null;
        },

        isNew() {
            return this.draft !== null && this.draft.id === null;
        },

        /**
         * A locked item is the declaration of a core module: it can be customized and
         * hidden, but the navigation must not lose it.
         */
        isLocked() {
            return this.draft !== null && this.draft.id !== null && this.selectedItem?.sysLocked === true;
        },

        typeOptions() {
            return [
                TYPE_MENU,
                TYPE_FOLDER,
                TYPE_LINK,
                TYPE_HIDDEN,
            ].map((value) => {
                return { value, label: this.$t(`sw-system.menu.types.${value}`) };
            });
        },

        /**
         * Only the identifiers matching the item type are offered: a folder points at a module
         * navigation id, a menu at a route. The select is an allowlist, so a typo cannot produce
         * an item that the server rejects afterwards.
         */
        routeOptions() {
            const wanted = this.draft?.type === TYPE_FOLDER ? TYPE_FOLDER : TYPE_MENU;

            return this.routeAllowlist
                .filter((route) => route.type === wanted)
                .map((route) => {
                    return {
                        value: route.internalKey,
                        label: `${route.title} (${route.internalKey})`,
                    };
                });
        },

        /**
         * Folder items only: a menu, link or hidden item cannot have children, so offering
         * them as a parent would offer a move the tree blocks as well.
         */
        parentOptions() {
            // An item cannot become its own parent, and a folder cannot be moved below one of
            // its own children - both would detach the subtree from the navigation.
            const blocked = this.draft?.id ? [this.draft.id, ...this.descendantIds(this.draft.id)] : [];

            const options = [{ value: '', label: this.$t('sw-system.menu.parentRoot') }];

            const walk = (nodes, depth) => {
                nodes.forEach((node) => {
                    if (node.type === TYPE_FOLDER && !blocked.includes(node.id)) {
                        options.push({
                            value: node.id,
                            label: `${'— '.repeat(depth + 1)}${this.labelOf(node)}`,
                        });
                    }

                    walk(node.children, depth + 1);
                });
            };

            walk(this.tree, 0);

            return options;
        },

        parentIdValue() {
            return this.draft?.parentId ?? '';
        },

        parentId() {
            return this.parentIdValue === '' ? null : this.parentIdValue;
        },

        positionOptions() {
            const options = [{ value: '', label: this.$t('sw-system.menu.positionFirst') }];

            const siblings = this.store.siblingsOf(this.parentId) ?? [];

            siblings.forEach((sibling) => {
                if (sibling.id === this.draft?.id) {
                    return;
                }

                options.push({ value: sibling.id, label: this.labelOf(sibling) });
            });

            return options;
        },

        positionValue() {
            return this.draft?.afterMenuItemId ?? '';
        },

        /**
         * Menu items carry a role key like `product.viewer`, the same identifier the
         * navigation declarations use. The list comes from the privileges service, so a
         * plugin's own privilege group is offered without registering it here.
         */
        privilegeOptions() {
            const options = [{ value: '', label: this.$t('sw-system.menu.privilegeNone') }];

            this.privileges.getPrivilegesMappings().forEach((mapping) => {
                if (mapping.key === null) {
                    return;
                }

                Object.keys(mapping.roles).forEach((role) => {
                    const value = `${mapping.key}.${role}`;

                    options.push({
                        value,
                        label: `${this.$t(`sw-privileges.permissions.${mapping.key}.label`)} · ${this.$t(`sw-privileges.roles.${role}`)}`,
                    });
                });
            });

            return options;
        },

        privilegeValue() {
            return this.draft?.privilege ?? '';
        },

        /**
         * Deleting an item removes every row below it: `fk.admin_menu_item.parent_id` cascades.
         * The confirmation therefore names the subtree size instead of leaving the user to
         * discover it in the tree afterwards.
         */
        descendantCount() {
            if (!this.selectedId) {
                return 0;
            }

            return this.descendantIds(this.selectedId).length;
        },

        deleteConfirmMessage() {
            if (this.descendantCount === 0) {
                return this.$t('sw-system.menu.deleteConfirmMessage');
            }

            return this.$t('sw-system.menu.deleteConfirmMessageWithChildren', { count: this.descendantCount });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.store.load().catch(() => {
                this.createNotificationError({ message: this.$t('sw-system.menu.notification.loadError') });
            });
        },

        /**
         * Same rule as the tree row: a core item stores a snippet key, an item created in
         * the administration stores plain text. Both have to read as a label here.
         */
        labelOf(item) {
            if (!item.title) {
                return item.internalKey || this.$t('sw-system.menu.labelUntitled');
            }

            return this.tWithFallback(item.title);
        },

        descendantIds(itemId) {
            const collected = [];

            const walk = (nodes) => {
                nodes.forEach((node) => {
                    collected.push(node.id);
                    walk(node.children);
                });
            };

            const findNode = (nodes) => {
                for (const node of nodes) {
                    if (node.id === itemId) {
                        return node;
                    }

                    const found = findNode(node.children);

                    if (found) {
                        return found;
                    }
                }

                return null;
            };

            const start = findNode(this.tree);
            walk(start ? start.children : []);

            return collected;
        },

        /**
         * Selecting another item drops the current draft, so an unsaved change asks first.
         */
        selectItem(itemId) {
            if (itemId === this.selectedId) {
                return;
            }

            if (this.isDirty) {
                this.pendingSelectionId = itemId;
                this.showDiscardModal = true;

                return;
            }

            this.applySelection(itemId);
        },

        applySelection(itemId) {
            const item = this.store.itemById(itemId);

            this.selectedId = itemId;
            this.draft = item ? createDraft({ ...item, roleIds: [...item.roleIds] }) : null;
            this.snapshot = this.draft ? JSON.stringify(this.draft) : null;
        },

        onCreateNew() {
            if (!this.canCreate) {
                return;
            }

            const parentId = this.selectedItem ? this.selectedItem.parentId : null;
            const afterMenuItemId = this.selectedItem ? this.selectedItem.id : null;

            this.selectedId = null;
            this.draft = createDraft({ parentId, afterMenuItemId });
            this.snapshot = JSON.stringify(this.draft);
        },

        /**
         * `parentId` and `afterMenuItemId` are one decision: the predecessor is only valid
         * within its own level, so changing the level drops it back to the head.
         */
        onParentChange(value) {
            if (!this.draft) {
                return;
            }

            this.draft.parentId = value === '' ? null : value;
            this.draft.afterMenuItemId = null;
        },

        onPositionChange(value) {
            if (!this.draft) {
                return;
            }

            this.draft.afterMenuItemId = value === '' ? null : value;
        },

        /**
         * The target of an item is type dependent: a route id on a `link` item points at
         * nothing, and a link address on a `menu` item is never read.
         */
        onTypeChange(value) {
            if (!this.draft) {
                return;
            }

            this.draft.type = value;

            if (value === TYPE_LINK || value === TYPE_HIDDEN) {
                this.draft.internalKey = null;
            }

            if (value !== TYPE_LINK) {
                this.draft.linkTarget = '';
            }
        },

        onDiscardConfirmed() {
            this.showDiscardModal = false;

            if (this.pendingSelectionId === null) {
                this.draft = null;
                this.snapshot = null;

                return;
            }

            const itemId = this.pendingSelectionId;
            this.pendingSelectionId = null;
            this.applySelection(itemId);
        },

        onDiscardCancelled() {
            this.showDiscardModal = false;
            this.pendingSelectionId = null;
        },

        validate() {
            if (!this.draft.title.trim()) {
                this.createNotificationError({ message: this.$t('sw-system.menu.notification.titleRequired') });

                return false;
            }

            if ((this.draft.type === TYPE_MENU || this.draft.type === TYPE_FOLDER) && !this.draft.internalKey) {
                this.createNotificationError({ message: this.$t('sw-system.menu.notification.targetRequired') });

                return false;
            }

            if (this.draft.type === TYPE_LINK && !this.draft.linkTarget.trim()) {
                this.createNotificationError({ message: this.$t('sw-system.menu.notification.linkRequired') });

                return false;
            }

            return true;
        },

        buildPayload() {
            return {
                id: this.draft.id ?? undefined,
                title: this.draft.title.trim(),
                icon: this.draft.icon || null,
                type: this.draft.type,
                internalKey: this.draft.internalKey || null,
                linkTarget: this.draft.type === TYPE_LINK ? this.draft.linkTarget.trim() : null,
                privilege: this.draft.privilege || null,
                active: this.draft.active,
                roles: this.draft.roleIds.map((id) => ({ id })),
            };
        },

        /**
         * A new item is always placed explicitly, an existing one only when its level or its
         * predecessor changed - a plain rename must not trigger a second write.
         */
        needsMove() {
            if (this.isNew) {
                return true;
            }

            return (
                this.selectedItem?.parentId !== this.parentId ||
                this.selectedItem?.afterMenuItemId !== this.draft.afterMenuItemId
            );
        },

        async onSave() {
            if (!this.draft || this.isSaving || !this.canEdit) {
                return;
            }

            if (!this.validate()) {
                return;
            }

            const needsMove = this.needsMove();

            this.isSaving = true;

            try {
                const id = await this.store.saveItem(this.buildPayload());

                if (needsMove && id) {
                    await this.store.moveItem({
                        id,
                        parentId: this.parentId,
                        afterMenuItemId: this.draft.afterMenuItemId,
                    });
                }

                this.applySelection(id);
                this.createNotificationSuccess({ message: this.$t('sw-system.menu.notification.saveSuccess') });
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-system.menu.notification.saveError'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        /**
         * The write validators of the server answer with constraint violations, which carry a
         * readable detail. Showing it beats a generic "could not be saved".
         */
        errorMessage(error, fallbackKey) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$t(fallbackKey);
        },

        async onMove(move) {
            try {
                await this.store.moveItem(move);
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-system.menu.notification.moveError'),
                });
            }
        },

        onDelete() {
            if (!this.selectedId || !this.canDelete || this.isLocked) {
                return;
            }

            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        async onConfirmDelete() {
            if (!this.selectedId) {
                return;
            }

            this.isDeleting = true;

            try {
                await this.store.deleteItem(this.selectedId);

                this.draft = null;
                this.snapshot = null;
                this.selectedId = null;
                this.showDeleteModal = false;

                this.createNotificationSuccess({ message: this.$t('sw-system.menu.notification.deleteSuccess') });
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-system.menu.notification.deleteError'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
};
