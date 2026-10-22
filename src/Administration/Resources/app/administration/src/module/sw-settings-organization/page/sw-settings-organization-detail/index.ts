/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-organization-detail.html.twig';
import './sw-settings-organization-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type OrganizationEntity = EntitySchema.Entity<'organization'>;
type OrganizationUnitEntity = EntitySchema.Entity<'organization_unit'>;

/**
 * The organization detail page: the unit tree on the left, the selected unit on the right.
 *
 * The unit form saves as the merchant edits - a unit has four fields and a save button behind a
 * language switch would bury every one of them. Moving a unit under a descendant is rejected in
 * the form itself: the parent select does not offer the unit or its subtree (AC-06), and a unit
 * whose parent is gone from the options keeps its current parent.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        organizationId: {
            type: String,
            required: true,
        },
    },

    data(): {
        organization: OrganizationEntity | null;
        units: OrganizationUnitEntity[];
        isLoading: boolean;
        selectedUnitId: string | null;
        isSavingUnit: boolean;
        showCreateUnitModal: boolean;
        newUnitParent: OrganizationUnitEntity | null;
        newUnitName: string;
        showDeleteModal: boolean;
        parentSearchTerm: string;
    } {
        return {
            organization: null,
            units: [],
            isLoading: false,
            selectedUnitId: null,
            isSavingUnit: false,
            showCreateUnitModal: false,
            newUnitParent: null,
            newUnitName: '',
            showDeleteModal: false,
            parentSearchTerm: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.organization?.name),
        };
    },

    computed: {
        organizationRepository(): RepositoryType<'organization'> {
            return this.repositoryFactory.create('organization');
        },

        unitRepository(): RepositoryType<'organization_unit'> {
            return this.repositoryFactory.create('organization_unit');
        },

        unitById(): Record<string, OrganizationUnitEntity> {
            const map: Record<string, OrganizationUnitEntity> = {};

            this.units.forEach((unit) => {
                map[unit.id] = unit;
            });

            return map;
        },

        selectedUnit(): OrganizationUnitEntity | null {
            return this.selectedUnitId ? this.unitById[this.selectedUnitId] ?? null : null;
        },

        descendantIdsOfSelected(): Record<string, boolean> {
            const collected: Record<string, boolean> = {};
            const stack: (string | null | undefined)[] = [this.selectedUnitId];

            while (stack.length > 0) {
                const currentId = stack.pop();

                if (!currentId || collected[currentId]) {
                    continue;
                }

                collected[currentId] = true;

                this.units
                    .filter((unit) => unit.parentId === currentId)
                    .forEach((unit) => stack.push(unit.id));
            }

            return collected;
        },

        parentOptions(): Array<{ value: string; label: string }> {
            return this.units
                .filter((unit) => {
                    if (unit.id === this.selectedUnitId) {
                        return false;
                    }

                    return !(unit.id in this.descendantIdsOfSelected);
                })
                .map((unit) => ({
                    value: unit.id,
                    label: unit.translated?.name ?? unit.name ?? '',
                }));
        },

        canEdit(): boolean {
            return this.acl.can('organization.editor');
        },

        canCreate(): boolean {
            return this.acl.can('organization.creator');
        },

        canDelete(): boolean {
            return this.acl.can('organization.deleter');
        },
    },

    watch: {
        organizationId() {
            void this.createdComponent();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                const criteria: CriteriaType = new Criteria(1, 500);

                criteria.addFilter(Criteria.equals('organizationId', this.organizationId));
                criteria.addSorting(Criteria.sort('level', 'ASC'));

                const [organization, units] = await Promise.all([
                    this.organizationRepository.get(this.organizationId as EntitySchema.OrganizationId),
                    this.unitRepository.search(criteria),
                ]);

                this.organization = organization;
                this.units = [...units];
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onSelectUnit(unit: OrganizationUnitEntity): void {
            this.selectedUnitId = unit.id;
        },

        onAddChild(parentUnit: OrganizationUnitEntity): void {
            this.newUnitParent = parentUnit;
            this.newUnitName = '';
            this.showCreateUnitModal = true;
        },

        onAddRootUnit(): void {
            this.newUnitParent = null;
            this.newUnitName = '';
            this.showCreateUnitModal = true;
        },

        onCloseCreateUnitModal(): void {
            this.showCreateUnitModal = false;
            this.newUnitParent = null;
            this.newUnitName = '';
        },

        async onCreateUnitSave(): Promise<void> {
            if (this.newUnitName.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageUnitNameRequired'),
                });

                return;
            }

            const unit = this.unitRepository.create();

            unit.organizationId = this.organizationId as unknown as EntitySchema.OrganizationUnitId;
            // undefined for a new root unit, the parent id for a child unit
            unit.parentId = this.newUnitParent?.id;
            unit.name = this.newUnitName;
            unit.active = true;

            try {
                await this.unitRepository.save(unit);
                this.onCloseCreateUnitModal();
                await this.createdComponent();
                this.selectedUnitId = unit.id;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageUnitSaveError'),
                });
            }
        },

        async onParentChange(parentId: string): Promise<void> {
            const unit = this.selectedUnit;

            if (!unit) {
                return;
            }

            if (parentId === unit.id || parentId in this.descendantIdsOfSelected) {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.errorCircularParent'),
                });

                return;
            }

            // null must reach the DAL to detach the unit from its parent (move to root level)
            unit.parentId = (parentId || null) as unknown as EntitySchema.OrganizationUnitId;

            await this.onUnitFieldChange();
        },

        async onUnitFieldChange(): Promise<void> {
            const unit = this.selectedUnit;

            if (!unit || this.isSavingUnit) {
                return;
            }

            this.isSavingUnit = true;

            try {
                await this.unitRepository.save(unit);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageUnitSaveError'),
                });
            } finally {
                this.isSavingUnit = false;
            }
        },

        onOpenDeleteModal(): void {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal(): void {
            this.showDeleteModal = false;
        },

        async onConfirmDeleteUnit(): Promise<void> {
            this.showDeleteModal = false;

            const unit = this.selectedUnit;

            if (!unit) {
                return;
            }

            try {
                await this.unitRepository.delete(unit.id as EntitySchema.OrganizationUnitId);
                this.selectedUnitId = null;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.detail.messageUnitDeleteError'),
                });
            } finally {
                await this.createdComponent();
            }
        },
    },
});
