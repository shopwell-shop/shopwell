/**
 * @sw-package framework
 */
import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import { inject } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import useOrganizationTree from '../../composables/use-organization-tree';
import { getUnitPath, getDescendantIds } from '../../organization-unit-tree.util';
import type RepositoryType from 'src/core/data/repository.data';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-settings-organization-detail.html.twig';
import './sw-settings-organization-detail.scss';

const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();
type Organization = EntitySchema.Entity<'organization'>;
type Unit = EntitySchema.Entity<'organization_unit'>;
type DeleteTarget = { organization: Organization; unit?: never } | { unit: Unit; organization?: never };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl'],
    props: {
        organizationId: { type: String, default: null },
        unitId: { type: String, default: null },
        parentId: { type: String, default: null },
        createOrganization: { type: Boolean, default: false },
    },
    setup() {
        const factory = inject<RepositoryFactory>('repositoryFactory');
        if (!factory) {
            throw new Error('repositoryFactory is required');
        }
        return useOrganizationTree(factory.create('organization'), factory.create('organization_unit'));
    },
    data(): {
        organization: Organization | null;
        selectedUnitId: string | null;
        newUnit: Unit | null;
        isLoading: boolean;
        loadFailed: boolean;
        loadRequest: number;
        isSaving: boolean;
        isDeleting: boolean;
        deleteTarget: DeleteTarget | null;
        pendingAction: (() => void) | null;
        cancelPendingAction: (() => void) | null;
        nameInvalid: boolean;
        searchTerm: string;
    } {
        return {
            organization: null,
            selectedUnitId: null,
            newUnit: null,
            isLoading: false,
            loadFailed: false,
            loadRequest: 0,
            isSaving: false,
            isDeleting: false,
            deleteTarget: null,
            pendingAction: null,
            cancelPendingAction: null,
            nameInvalid: false,
            searchTerm: '',
        };
    },
    metaInfo() {
        return { title: this.$createTitle(this.identifier) };
    },
    computed: {
        organizationRepository(): RepositoryType<'organization'> {
            return this.repositoryFactory.create('organization');
        },
        unitRepository(): RepositoryType<'organization_unit'> {
            return this.repositoryFactory.create('organization_unit');
        },
        selectionKey(): string {
            return `${this.createOrganization}/${this.organizationId}/${this.unitId}/${this.parentId}`;
        },
        treeOrganizations(): Organization[] {
            const organizations = this.organizations.map((item) =>
                this.organization?.id === item.id ? this.organization : item,
            );
            if (this.organization && !organizations.some((item) => item.id === this.organization?.id)) {
                organizations.push(this.organization);
            }
            return organizations;
        },
        treeUnits(): Unit[] {
            return this.newUnit ? [...this.units, this.newUnit] : this.units;
        },
        organizationUnits(): Unit[] {
            return this.units.filter((unit) => unit.organizationId === this.organization?.id);
        },
        selectedUnit(): Unit | null {
            return this.newUnit ?? this.organizationUnits.find((unit) => unit.id === this.selectedUnitId) ?? null;
        },
        editableEntity(): Organization | Unit | null {
            return this.selectedUnit ?? this.organization;
        },
        identifier(): string {
            return (
                this.editableEntity?.name ||
                this.editableEntity?.translated?.name ||
                this.$t(
                    this.selectedUnit
                        ? 'sw-settings-organization.detail.titleCreateUnit'
                        : this.createOrganization
                          ? 'sw-settings-organization.list.modalTitleCreate'
                          : 'sw-settings-organization.general.mainMenuItemGeneral',
                )
            );
        },
        descendantIdsOfSelected(): Set<string> {
            return getDescendantIds(this.treeUnits, this.selectedUnitId);
        },
        parentOptions(): Array<{ value: string; label: string }> {
            return [
                { value: '', label: this.$t('sw-settings-organization.detail.parentRoot') },
                ...this.organizationUnits
                    .filter((unit) => !this.descendantIdsOfSelected.has(unit.id))
                    .map((unit) => ({ value: unit.id, label: getUnitPath(this.organizationUnits, unit.id).join(' / ') })),
            ];
        },
        canCreate(): boolean {
            return this.acl.can('organization.creator');
        },
        canDelete(): boolean {
            return this.acl.can('organization.deleter');
        },
        allowSave(): boolean {
            return this.newUnit || this.createOrganization ? this.canCreate : this.acl.can('organization.editor');
        },
        hasChanges(): boolean {
            if (this.selectedUnit) {
                return !!this.newUnit || this.unitRepository.hasChanges(this.selectedUnit);
            }
            return (
                !!this.organization && (this.createOrganization || this.organizationRepository.hasChanges(this.organization))
            );
        },
        isBusy(): boolean {
            return this.isLoading || this.isSaving || this.isDeleting;
        },
        deleteConfirmation(): string {
            const entity = this.deleteTarget?.unit ?? this.deleteTarget?.organization;
            return this.$t(
                this.deleteTarget?.unit
                    ? 'sw-settings-organization.detail.textDeleteUnitConfirm'
                    : 'sw-settings-organization.list.textDeleteConfirm',
                { name: entity?.name ?? entity?.translated?.name },
                0,
            );
        },
        ...mapPropertyErrors('editableEntity', ['name', 'parentId']),
    },
    watch: {
        selectionKey() {
            void this.createdComponent();
        },
    },
    created() {
        void this.createdComponent();
    },
    beforeRouteLeave(_to, _from, next: NavigationGuardNext) {
        this.confirmRouteNavigation(next);
    },
    beforeRouteUpdate(_to: RouteLocationNormalized, _from: RouteLocationNormalized, next: NavigationGuardNext) {
        this.confirmRouteNavigation(next);
    },
    methods: {
        async createdComponent(): Promise<void> {
            const request = ++this.loadRequest;
            this.isLoading = true;
            this.loadFailed = false;
            this.nameInvalid = false;
            this.newUnit = null;
            this.selectedUnitId = null;
            try {
                if (this.createOrganization) {
                    Shopwell.Store.get('context').resetLanguageToDefault();
                }
                await this.loadNavigation();
                if (request !== this.loadRequest) {
                    return;
                }
                if (this.createOrganization) {
                    this.organization = this.organizationRepository.create();
                    this.organization.active = true;
                } else if (this.organizationId) {
                    const [organization] = await Promise.all([
                        this.organizationRepository.get(this.organizationId as EntitySchema.OrganizationId),
                        this.loadOrganizationUnits(this.organizationId),
                    ]);
                    if (request !== this.loadRequest) {
                        return;
                    }
                    if (!organization) {
                        throw new Error('Organization was not found');
                    }
                    this.organization = organization;
                    if (this.unitId === 'create') {
                        if (this.parentId && !this.organizationUnits.some((unit) => unit.id === this.parentId)) {
                            throw new Error('Parent unit does not belong to this organization');
                        }
                        this.newUnit = this.unitRepository.create();
                        this.newUnit.organizationId = organization.id;
                        this.newUnit.parentId = this.parentId as EntitySchema.OrganizationUnitId;
                        this.newUnit.active = true;
                        this.selectedUnitId = this.newUnit.id;
                    } else {
                        this.selectedUnitId = this.unitId;
                        if (this.unitId && !this.selectedUnit) {
                            throw new Error('Organization unit was not found');
                        }
                    }
                } else {
                    this.organization = null;
                }
            } catch {
                if (request === this.loadRequest) {
                    this.organization = null;
                    this.newUnit = null;
                    this.loadFailed = true;
                    useNotification().createNotificationError({
                        message: this.$t('sw-settings-organization.detail.messageLoadError'),
                    });
                }
            } finally {
                if (request === this.loadRequest) {
                    this.isLoading = false;
                }
            }
        },
        async onLoadOrganization(id: string): Promise<void> {
            try {
                await this.loadOrganizationUnits(id);
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageLoadError'),
                });
            }
        },
        async onSearch(term: string): Promise<void> {
            this.searchTerm = term;
            try {
                await this.searchTree(term);
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageLoadError'),
                });
            }
        },
        resetEntityChanges(): void {
            if (this.newUnit) {
                this.selectedUnitId = this.newUnit.parentId ?? null;
                this.newUnit = null;
            } else if (this.selectedUnit) {
                this.unitRepository.discard(this.selectedUnit);
            } else if (this.organization) {
                this.organizationRepository.discard(this.organization);
            }
            this.nameInvalid = false;
        },
        onCancel(): void {
            if (this.isBusy) {
                return;
            }
            const parentId = this.newUnit?.parentId ?? null;
            const wasUnit = !!this.selectedUnit;
            this.resetEntityChanges();
            if (wasUnit && this.organizationId) {
                void this.$router.push({
                    name: 'sw.settings.organization.detail',
                    params: { id: this.organizationId },
                    query: parentId ? { unitId: parentId } : {},
                });
            } else {
                this.organization = null;
                void this.$router.push({ name: 'sw.settings.organization.index' });
            }
        },
        confirmRouteNavigation(next: NavigationGuardNext): void {
            if (this.isBusy) {
                next(false);
                return;
            }
            if (this.hasChanges) {
                this.keepEditing();
                this.pendingAction = () => next();
                this.cancelPendingAction = () => next(false);
            } else {
                next();
            }
        },
        keepEditing(): void {
            const cancel = this.cancelPendingAction;
            this.pendingAction = null;
            this.cancelPendingAction = null;
            cancel?.();
        },
        discardChanges(): void {
            const action = this.pendingAction;
            this.pendingAction = null;
            this.cancelPendingAction = null;
            this.resetEntityChanges();
            action?.();
        },
        onSelectOrganization(organization: Organization): void {
            if (!this.isBusy && !organization.isNew()) {
                void this.$router.push({ name: 'sw.settings.organization.detail', params: { id: organization.id } });
            }
        },
        onSelectUnit(unit: Unit): void {
            if (!this.isBusy && !unit.isNew()) {
                void this.$router.push({
                    name: 'sw.settings.organization.detail',
                    params: { id: unit.organizationId },
                    query: { unitId: unit.id },
                });
            }
        },
        onAddOrganization(): void {
            if (this.canCreate && !this.isBusy) {
                void this.$router.push({ name: 'sw.settings.organization.create' });
            }
        },
        onAddRootUnit(organization?: Organization): void {
            const target = organization ?? this.organization;
            if (target && !target.isNew()) {
                this.onAddUnit(target.id, null);
            }
        },
        onAddChild(parent: Unit): void {
            if (!parent.isNew()) {
                this.onAddUnit(parent.organizationId, parent.id);
            }
        },
        onAddUnit(organizationId: string, parentId: string | null): void {
            if (this.canCreate && !this.isBusy) {
                void this.$router.push({
                    name: 'sw.settings.organization.detail',
                    params: { id: organizationId },
                    query: { unitId: 'create', ...(parentId ? { parentId } : {}) },
                });
            }
        },
        onParentChange(parentId: string | null): void {
            if (this.selectedUnit && (!parentId || this.organizationUnits.some((unit) => unit.id === parentId))) {
                this.selectedUnit.parentId = (parentId || null) as EntitySchema.OrganizationUnitId;
            }
        },
        abortOnLanguageChange(): boolean {
            return this.hasChanges || this.isBusy;
        },
        async onChangeLanguage(): Promise<void> {
            this.units = [];
            this.loadedOrganizationIds = [];
            await this.createdComponent();
        },
        async saveOnLanguageChange(): Promise<void> {
            if (!(await this.onSave())) {
                throw new Error('Organization changes could not be saved');
            }
        },
        canSaveEntity(): boolean {
            return this.allowSave && !this.isBusy && !!this.editableEntity;
        },
        async onSave(): Promise<boolean> {
            if (!this.canSaveEntity()) {
                return false;
            }
            const entity = this.editableEntity;
            if (!entity) {
                return false;
            }
            this.nameInvalid = !entity.name?.trim();
            if (this.nameInvalid) {
                return false;
            }
            if (this.selectedUnit && this.descendantIdsOfSelected.has(this.selectedUnit.parentId ?? '')) {
                return false;
            }
            entity.name = entity.name.trim();
            const wasNewUnit = !!this.newUnit;
            const wasUnit = !!this.selectedUnit;
            this.isSaving = true;
            try {
                if (wasUnit) {
                    await this.unitRepository.save(entity as Unit);
                } else {
                    await this.organizationRepository.save(entity as Organization);
                }
                this.loadedOrganizationIds = this.loadedOrganizationIds.filter((id) => id !== this.organizationId);
                if (this.createOrganization || wasNewUnit) {
                    this.newUnit = null;
                    this.selectedUnitId = null;
                    this.organization = null;
                    this.isSaving = false;
                    await this.$router.replace({
                        name: 'sw.settings.organization.detail',
                        params: { id: wasUnit ? this.organizationId : entity.id },
                        query: wasUnit ? { unitId: entity.id } : {},
                    });
                } else {
                    await this.createdComponent();
                }
                return !this.loadFailed;
            } catch {
                useNotification().createNotificationError({
                    message: this.$t(
                        wasUnit
                            ? 'sw-settings-organization.detail.messageUnitSaveError'
                            : 'sw-settings-organization.list.messageSaveError',
                    ),
                });
                return false;
            } finally {
                this.isSaving = false;
            }
        },
        onDeleteUnit(unit: Unit): void {
            if (this.canDelete && !this.isBusy && !unit.isNew()) {
                this.deleteTarget = { unit };
            }
        },
        onDeleteOrganization(organization: Organization): void {
            if (this.canDelete && !this.isBusy && !organization.isNew()) {
                this.deleteTarget = { organization };
            }
        },
        onCloseDeleteModal(): void {
            if (!this.isDeleting) {
                this.deleteTarget = null;
            }
        },
        async onConfirmDelete(): Promise<void> {
            const target = this.deleteTarget;
            if (!target || !this.canDelete || this.isBusy) {
                return;
            }
            this.isDeleting = true;
            try {
                const organizationId = target.unit ? target.unit.organizationId : target.organization.id;
                const removedIds = target.unit
                    ? getDescendantIds(this.treeUnits, target.unit.id)
                    : new Set(
                          this.treeUnits.filter((unit) => unit.organizationId === organizationId).map((unit) => unit.id),
                      );
                if (target.unit) {
                    await this.unitRepository.delete(target.unit.id);
                } else {
                    await this.organizationRepository.delete(target.organization.id);
                }
                this.deleteTarget = null;
                const selectionRemoved =
                    (this.selectedUnitId && removedIds.has(this.selectedUnitId)) ||
                    (!target.unit && this.organizationId === organizationId);
                this.units = this.units.filter((unit) => !removedIds.has(unit.id));
                this.loadedOrganizationIds = this.loadedOrganizationIds.filter((id) => id !== organizationId);
                await this.loadNavigation();
                if (selectionRemoved) {
                    this.newUnit = null;
                    this.selectedUnitId = null;
                    if (!target.unit) {
                        this.organization = null;
                    }
                    this.isDeleting = false;
                    await this.$router.replace(
                        target.unit
                            ? {
                                  name: 'sw.settings.organization.detail',
                                  params: { id: organizationId },
                                  query: target.unit.parentId ? { unitId: target.unit.parentId } : {},
                              }
                            : { name: 'sw.settings.organization.index' },
                    );
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageDeleteError'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
