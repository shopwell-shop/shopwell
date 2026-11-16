/** @sw-package fundamentals@framework */
import type { PropType } from 'vue';
import type { DepartmentAssignment, OrganizationAssignments } from '../../types/user-organization.types';
import template from './sw-user-organization-settings.html.twig';

const { Criteria } = Shopwell.Data;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory'],
    props: {
        value: { type: Object as PropType<OrganizationAssignments>, required: true },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:value'],
    data() {
        return {
            units: [] as Entity<'organization_unit'>[],
            positions: [] as Entity<'position'>[],
            loadFailed: false,
            requestId: 0,
        };
    },
    computed: {
        unitRepository() {
            return this.repositoryFactory.create('organization_unit');
        },
        positionRepository() {
            return this.repositoryFactory.create('position');
        },
        unitIds(): string[] {
            return this.value.departments.map((department) => department.organizationUnitId);
        },
        selectionKey(): string {
            return this.value.departments
                .map((department) => `${department.organizationUnitId}:${department.positionIds.join(',')}`)
                .join('/');
        },
        unitOptions() {
            return this.value.departments.map((department) => ({
                value: department.id,
                label: this.unitName(department.organizationUnitId),
            }));
        },
        primaryDepartment(): string | null {
            return (
                (this.value.departments.find((department) => department.isPrimary) ?? this.value.departments[0])?.id ?? null
            );
        },
        positionOptions() {
            return this.value.departments.flatMap((department) =>
                department.positionIds.map((positionId) => ({
                    value: `${department.organizationUnitId}:${positionId}`,
                    label: `${this.unitName(department.organizationUnitId)} / ${this.positionName(positionId)}`,
                })),
            );
        },
        primaryPosition(): string | null {
            return (
                this.positionOptions.find((option) => option.value === this.value.primaryPosition)?.value ??
                this.positionOptions[0]?.value ??
                null
            );
        },
    },
    watch: {
        selectionKey: {
            immediate: true,
            handler() {
                void this.loadSelection();
            },
        },
    },
    methods: {
        async loadSelection(): Promise<void> {
            const requestId = ++this.requestId;
            this.loadFailed = false;
            const positionIds = [...new Set(this.value.departments.flatMap((department) => department.positionIds))];
            try {
                const [units, positions] = await Promise.all([
                    this.unitIds.length ? this.unitRepository.search(new Criteria(1, 500).setIds(this.unitIds)) : [],
                    positionIds.length ? this.positionRepository.search(new Criteria(1, 500).setIds(positionIds)) : [],
                ]);
                if (requestId !== this.requestId) {
                    return;
                }
                this.units = [...units];
                this.positions = [...positions];
            } catch {
                if (requestId === this.requestId) {
                    this.loadFailed = true;
                }
            }
        },
        unitName(unitId: string): string {
            const unit = this.units.find((candidate) => candidate.id === unitId);
            return unit?.translated?.name ?? unit?.name ?? this.$t('sw-user.users.user-detail.organizationUnavailable');
        },
        positionName(positionId: string): string {
            const position = this.positions.find((candidate) => candidate.id === positionId);
            return position?.translated?.name ?? position?.name ?? this.$t('sw-user.users.user-detail.positionUnavailable');
        },
        changeUnits(unitIds: string[]): void {
            if (this.disabled) {
                return;
            }
            const departments = [...new Set(unitIds)].map(
                (organizationUnitId) =>
                    this.value.departments.find((department) => department.organizationUnitId === organizationUnitId) ?? {
                        id: Shopwell.Utils.createId(),
                        organizationUnitId,
                        isPrimary: false,
                        isLeader: false,
                        leaderPosition: 0,
                        supervisorMembershipId: null,
                        positionIds: [],
                    },
            );
            if (departments.length > 0 && !departments.some((department) => department.isPrimary)) {
                departments[0] = { ...departments[0], isPrimary: true };
            }
            this.emitAssignments(departments);
        },
        changePrimaryDepartment(id: string): void {
            if (!this.disabled) {
                this.emitAssignments(
                    this.value.departments.map((department) => ({ ...department, isPrimary: department.id === id })),
                );
            }
        },
        changePrimaryPosition(primaryPosition: string): void {
            if (!this.disabled) {
                this.$emit('update:value', { ...this.value, primaryPosition });
            }
        },
        updateDepartment(id: string, changes: Partial<DepartmentAssignment>): void {
            if (!this.disabled) {
                this.emitAssignments(
                    this.value.departments.map((department) =>
                        department.id === id ? { ...department, ...changes } : department,
                    ),
                );
            }
        },
        emitAssignments(departments: DepartmentAssignment[]): void {
            const scopes = departments.flatMap((department) =>
                department.positionIds.map((id) => `${department.organizationUnitId}:${id}`),
            );
            const primaryPosition = scopes.find((scope) => scope === this.value.primaryPosition) ?? scopes[0] ?? null;
            this.$emit('update:value', { departments, primaryPosition });
        },
    },
});
