/**
 * @sw-package fundamentals@framework
 */
import template from './sw-system-menu-list.html.twig';
import './sw-system-menu-list.scss';
import { buildPayload, createDraft, fingerprintDraft, needsMove } from './menu-draft';
import { collectAncestorIds, collectBranchIds, collectDescendantIds, filterTreeByTerm, findNode } from './tree-filter';

const { Data, Mixin } = Shopwell;
const { Criteria } = Data;

const TYPE_MENU = 'menu';
const TYPE_FOLDER = 'folder';
const TYPE_LINK = 'link';
const TYPE_HIDDEN = 'hidden';

/**
 * The management page of the database driven navigation.
 *
 * The page follows the navigation pattern of the category page: the stored navigation lives
 * in the side content of the page, the content holds the form of the selected entry, and the
 * smart bar carries the language and the save decision. The entries are only labels plus a
 * place, so the form is one set of cards instead of a routed detail page.
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
            searchTerm: '',
            selectedId: null,
            languageId: Shopwell.Context.api.languageId,
            languages: [],
            showDeleteModal: false,
            draft: null,
            draftBaseline: null,
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

        isSearching() {
            return this.searchTerm.trim().length > 0;
        },

        /**
         * The tree a search shows: while a term is entered, only the matching entries and
         * their ancestors stay visible.
         */
        filteredTree() {
            const term = this.searchTerm.trim();

            if (!term) {
                return this.tree;
            }

            return filterTreeByTerm(this.tree, term);
        },

        /**
         * A search result is useless when its parents are closed, so while searching every
         * branch of the filtered tree is open - the operator's own expansion wins again the
         * moment the term is cleared.
         */
        effectiveExpandedIds() {
            if (!this.isSearching) {
                return this.expandedIds;
            }

            return collectBranchIds(this.filteredTree);
        },

        hasTree() {
            return this.filteredTree.length > 0;
        },

        /**
         * The entry the detail pane shows. The search may hide it from the panel, but the
         * selection itself survives so an edit never loses its subject.
         */
        selectedItem() {
            return findNode(this.tree, this.selectedId);
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
         * Editing an existing entry needs the editor privilege, creating one the creator
         * privilege - the smart bar offers the same button for both, so it asks which one
         * applies to the draft.
         */
        canSaveDraft() {
            return this.selectedItem ? this.canEdit : this.canCreate;
        },

        isAllExpanded() {
            return this.expandableIds.length > 0 && this.expandableIds.every((id) => this.expandedIds.includes(id));
        },

        expandableIds() {
            return collectBranchIds(this.tree);
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
            const blocked = this.draft?.id ? [this.draft.id, ...collectDescendantIds(this.tree, this.draft.id)] : [];

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

        /**
         * `parentId` and `afterMenuItemId` are one decision: the predecessor is only valid
         * within its own level, so changing the level drops it back to the head.
         */
        parentId() {
            return this.parentIdValue === '' ? null : this.parentIdValue;
        },

        /**
         * A group is a property of a top level entry: it decides which heading the entry is
         * listed under, so the fields are only offered where the decision exists.
         */
        isTopLevel() {
            return this.parentIdValue === '';
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

        /**
         * Deleting an item removes every row below it: `fk.admin_menu_item.parent_id` cascades.
         * The confirmation therefore names the subtree size instead of leaving the user to
         * discover it in the tree afterwards.
         */
        deleteConfirmMessage() {
            const count = this.deleteTarget ? collectDescendantIds(this.tree, this.deleteTarget.id).length : 0;

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
         * list for the help texts - the switch itself loads the languages on its own.
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

        onSearch(value) {
            this.searchTerm = value;
        },

        /**
         * The labels of the shown language are edited through the draft, so a language switch
         * re-reads the stored translation instead of renaming the entry in the wrong language.
         * Unsaved edits would be lost by that, so the switch asks first.
         */
        abortOnLanguageChange() {
            return this.hasUnsavedChanges();
        },

        saveOnLanguageChange() {
            return this.hasUnsavedChanges() ? this.onSave() : Promise.resolve();
        },

        onLanguageChange(languageId) {
            this.languageId = languageId;

            if (this.selectedId) {
                this.initDraft(findNode(this.tree, this.selectedId));
            }
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
         * Selecting an entry means editing it: the detail pane is the form, filled from the
         * stored state of the entry in the language the page shows. The parents are opened so
         * a selection made from a search result is visible in the tree.
         */
        onItemSelect(item) {
            this.selectedId = item.id;

            const ancestors = collectAncestorIds(this.tree, item.id);

            this.expandedIds = [...new Set([...this.expandedIds, ...ancestors])];

            this.initDraft(findNode(this.tree, item.id));
        },

        initDraft(item) {
            if (!item) {
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
            this.draftBaseline = fingerprintDraft(this.draft);
        },

        setDraft(draft) {
            this.draft = draft;
            this.draftBaseline = fingerprintDraft(draft);
        },

        hasUnsavedChanges() {
            return this.draft !== null && fingerprintDraft(this.draft) !== this.draftBaseline;
        },

        onCreateNew() {
            if (!this.canCreate) {
                return;
            }

            this.selectedId = null;
            this.setDraft(createDraft());
        },

        /**
         * Creating from a row of the tree means the place is already decided: the form opens
         * with the level and the predecessor prefilled instead of asking the operator to
         * reconstruct them from selects.
         */
        onCreateAbove(item) {
            if (!this.canCreate) {
                return;
            }

            this.selectedId = null;
            this.setDraft(
                createDraft({
                    parentId: item.parentId,
                    afterMenuItemId: item.afterMenuItemId,
                }),
            );
        },

        onCreateBelow(item) {
            if (!this.canCreate) {
                return;
            }

            this.selectedId = null;
            this.setDraft(
                createDraft({
                    parentId: item.parentId,
                    afterMenuItemId: item.id,
                }),
            );
        },

        /**
         * Only folders are offered the entry, so a child can never hang below a leaf.
         */
        onAddChild(item) {
            if (!this.canCreate) {
                return;
            }

            this.selectedId = null;
            this.setDraft(createDraft({ parentId: item.id }));
        },

        /**
         * Cancelling restores the stored state: a selected entry keeps its selection with the
         * form showing what is stored, a new entry leaves the form entirely.
         */
        onCancelEdit() {
            if (this.selectedId) {
                this.initDraft(findNode(this.tree, this.selectedId));

                return;
            }

            this.draft = null;
            this.draftBaseline = null;
        },

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

        async onSave() {
            if (!this.draft || this.isSaving || (!this.canEdit && !this.canCreate)) {
                return;
            }

            if (!this.validate()) {
                return;
            }

            const hasToMove = needsMove(this.draft, this.store.itemById(this.draft.id), this.parentId);

            this.isSaving = true;

            try {
                const id = await this.store.saveItem(buildPayload(this.draft, this.languageId));

                if (hasToMove && id) {
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

                if (id) {
                    this.selectedId = id;
                    this.initDraft(findNode(this.tree, id));
                }

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

                if (move.id === this.selectedId) {
                    this.initDraft(findNode(this.tree, this.selectedId));
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

                if (this.selectedId === this.deleteTarget.id) {
                    this.selectedId = null;
                    this.draft = null;
                    this.draftBaseline = null;
                }

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
