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
        groupName: '',
        groupTitle: '',
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
 * The stored navigation is a tree and the whole page is that tree: the entries are only
 * labels plus a place, so a second pane that repeats them would cost the width the tree
 * needs for the indentation. Editing happens in a dialog, one entry at a time.
 *
 * A drop in the tree is a move request, not a local reorder:
 * `AdminMenuConfigService::move()` repairs the ordering chain of both affected levels, so a
 * reorder must never be computed on the client as well.
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
            expandedIds: [],
            languageId: Shopwell.Context.api.languageId,
            languages: [],
            showEditModal: false,
            showDeleteModal: false,
            draft: null,
            deleteTarget: null,
            isSaving: false,
            isDeleting: false,
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

        /**
         * The stored tree with the label of the selected language resolved - the tree
         * component itself only knows rows, places and labels, never a store or a translation.
         */
        tree() {
            return this.toRenderNodes(this.store.tree);
        },

        routeAllowlist() {
            return this.store.routeAllowlist;
        },

        isLoading() {
            return this.store.isLoading && !this.store.isLoaded;
        },

        hasTree() {
            return this.tree.length > 0;
        },

        /**
         * The language the labels are shown and edited in - named in the field help, so an
         * edit never lands in a language the operator did not mean.
         */
        languageLabel() {
            const language = this.languages.find((candidate) => candidate.id === this.languageId);

            return language?.name ?? Shopwell.Store.get('session').currentLocale;
        },

        canEdit() {
            return this.acl.can('admin_menu.editor');
        },

        canCreate() {
            return this.acl.can('admin_menu.creator');
        },

        canDelete() {
            return this.acl.can('admin_menu.deleter');
        },

        /**
         * The languages an item can be labelled in. A shop that runs a single language gets no
         * switch - and no label for a decision that does not exist.
         */
        languageOptions() {
            return this.languages.map((language) => {
                return { value: language.id, label: language.name };
            });
        },

        showLanguageSwitch() {
            return this.languages.length > 1;
        },

        isAllExpanded() {
            return this.expandableIds.length > 0 && this.expandableIds.every((id) => this.expandedIds.includes(id));
        },

        expandableIds() {
            const collected = [];

            const walk = (nodes) => {
                nodes.forEach((node) => {
                    if (node.children.length > 0) {
                        collected.push(node.id);
                        walk(node.children);
                    }
                });
            };

            walk(this.tree);

            return collected;
        },

        aclRoleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleCriteria() {
            return new Criteria(1, 500);
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
                        label: `${this.tWithFallback(route.title)} (${route.internalKey})`,
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
                            label: `${'— '.repeat(depth + 1)}${node.label}`,
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

        groupOptions() {
            // the groups a stored entry carries, so a rename of the free text field does not
            // have to be retyped from memory
            const known = new Set();

            this.items.forEach((item) => {
                if (item.groupName) {
                    known.add(item.groupName);
                }
            });

            return [...known].sort().map((group) => {
                return { value: group, label: this.groupLabelOf(group) };
            });
        },

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
         * A locked item is the declaration of a core module: it can be customized and
         * hidden, but the navigation must not lose it.
         */
        isLocked() {
            if (!this.draft?.id) {
                return false;
            }

            return this.store.itemById(this.draft.id)?.sysLocked === true;
        },

        isNew() {
            return this.draft !== null && this.draft.id === null;
        },

        editModalTitle() {
            return this.isNew ? this.$t('sw-system.menu.createModalTitle') : this.$t('sw-system.menu.editModalTitle');
        },

        /**
         * Deleting an item removes every row below it: `fk.admin_menu_item.parent_id` cascades.
         * The confirmation therefore names the subtree size instead of leaving the user to
         * discover it in the tree afterwards.
         */
        deleteConfirmMessage() {
            const count = this.deleteTarget ? this.descendantIds(this.deleteTarget.id).length : 0;

            if (count === 0) {
                return this.$t('sw-system.menu.deleteConfirmMessage');
            }

            return this.$t('sw-system.menu.deleteConfirmMessageWithChildren', { count });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadLanguages();
            this.loadTree();
        },

        loadTree() {
            this.store.load().catch(() => {
                this.createNotificationError({ message: this.$t('sw-system.menu.notification.loadError') });
            });
        },

        /**
         * The page labels entries in the language the operator picks, so it needs the language
         * list itself - the administration wide language switch would reload the whole client
         * for a label.
         */
        loadLanguages() {
            const repository = this.repositoryFactory.create('language');

            repository.search(new Criteria(1, 100), Shopwell.Context.api).then((languages) => {
                this.languages = languages;

                if (!this.languages.some((language) => language.id === this.languageId)) {
                    this.languageId = Shopwell.Context.api.languageId;
                }
            });
        },

        toRenderNodes(nodes) {
            return nodes.map((node) => {
                return {
                    ...node,
                    label: this.labelOf(node),
                    groupLabel: this.groupLabelOf(node.groupName, node),
                    children: this.toRenderNodes(node.children),
                };
            });
        },

        /**
         * A stored title wins, an entry an administrator did not rename keeps the label the
         * module declares - the database only stores what a person wrote, everything else is
         * still the code's.
         */
        labelOf(item) {
            const stored = item.translations?.[this.languageId]?.title;

            if (stored) {
                return stored;
            }

            if (item.title) {
                return this.tWithFallback(item.title);
            }

            const declared = this.routeAllowlist.find((route) => route.internalKey === item.internalKey);

            if (declared?.title) {
                return this.tWithFallback(declared.title);
            }

            return item.internalKey || this.$t('sw-system.menu.labelUntitled');
        },

        groupLabelOf(groupName, item = {}) {
            if (!groupName) {
                return null;
            }

            const stored = item.translations?.[this.languageId]?.groupTitle;

            if (stored) {
                return stored;
            }

            const key = `sw-system.menu.groups.${groupName}`;

            return this.$te(key) ? this.$t(key) : groupName;
        },

        descendantIds(itemId) {
            const collected = [];
            const flat = this.toRenderNodes(this.store.tree);

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

            const start = findNode(flat);
            walk(start ? start.children : []);

            return collected;
        },

        onLanguageChange(languageId) {
            this.languageId = languageId;
        },

        onToggle(item) {
            this.expandedIds = this.expandedIds.includes(item.id)
                ? this.expandedIds.filter((id) => id !== item.id)
                : [...this.expandedIds, item.id];
        },

        onToggleAll() {
            this.expandedIds = this.isAllExpanded ? [] : [...this.expandableIds];
        },

        /**
         * The dialog edits the labels of the language the page shows - switching the language
         * with an open dialog would silently rename the entry in another language.
         */
        onEdit(item) {
            if (!this.canEdit) {
                return;
            }

            const stored = item.translations?.[this.languageId] ?? {};

            this.draft = createDraft({
                ...item,
                title: item.label,
                groupName: item.groupName ?? '',
                groupTitle: stored.groupTitle ?? '',
                roleIds: [...item.roleIds],
            });

            this.showEditModal = true;
        },

        onCreateNew() {
            if (!this.canCreate) {
                return;
            }

            this.draft = createDraft();
            this.showEditModal = true;
        },

        onCloseEditModal() {
            this.showEditModal = false;
            this.draft = null;
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

        /**
         * The labels are translated fields, so they are written for the language the page shows
         * instead of for the language the client happens to run in.
         */
        buildPayload() {
            const translation = {
                languageId: this.languageId,
                title: this.draft.title.trim(),
                groupTitle: this.draft.groupTitle.trim() || null,
            };

            return {
                id: this.draft.id ?? undefined,
                translations: [translation],
                groupName: this.draft.groupName.trim() || null,
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

            const item = this.store.itemById(this.draft.id);

            return item?.parentId !== this.parentId || item?.afterMenuItemId !== this.draft.afterMenuItemId;
        },

        async onSave() {
            if (!this.draft || this.isSaving || !this.canEdit && !this.canCreate) {
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

                    // the entry was placed somewhere the operator cannot see yet
                    if (this.parentId && !this.expandedIds.includes(this.parentId)) {
                        this.expandedIds = [...this.expandedIds, this.parentId];
                    }
                }

                this.showEditModal = false;
                this.draft = null;

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

                if (move.parentId && !this.expandedIds.includes(move.parentId)) {
                    this.expandedIds = [...this.expandedIds, move.parentId];
                }
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-system.menu.notification.moveError'),
                });
            }
        },

        onDelete(item) {
            if (!this.canDelete || item.sysLocked) {
                return;
            }

            this.deleteTarget = item;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.deleteTarget = null;
        },

        async onConfirmDelete() {
            if (!this.deleteTarget) {
                return;
            }

            this.isDeleting = true;

            try {
                await this.store.deleteItem(this.deleteTarget.id);

                this.expandedIds = this.expandedIds.filter((id) => id !== this.deleteTarget.id);
                this.showDeleteModal = false;
                this.deleteTarget = null;

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
