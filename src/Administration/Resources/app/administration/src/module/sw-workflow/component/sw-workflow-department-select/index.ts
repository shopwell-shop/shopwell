/** @sw-package framework */
import type { PropType } from 'vue';
import template from './sw-workflow-department-select.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl'],
    props: {
        modelValue: { type: [String, Array] as PropType<string | string[]>, default: '' },
        displayOnly: { type: Boolean, default: false },
        multiple: { type: Boolean, default: false },
        candidateIds: { type: Array as PropType<string[]>, default: null },
        label: { type: String, required: true },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:model-value'],
    data() {
        return { units: [] as Entity<'organization_unit'>[], loading: false, failed: false, revision: 0 };
    },
    computed: {
        canRead(): boolean {
            return this.acl.can('organization_unit:read') && this.acl.can('organization:read');
        },
        ids(): string[] {
            return Array.isArray(this.modelValue) ? this.modelValue : this.modelValue ? [this.modelValue] : [];
        },
        repository() {
            return this.repositoryFactory.create('organization_unit');
        },
        criteria() {
            const { Criteria } = Shopwell.Data;
            const criteria = new Criteria(1, 25)
                .addAssociation('organization')
                .addFilter(Criteria.equals('active', true))
                .addFilter(Criteria.equals('organization.active', true))
                .addSorting(Criteria.sort('name', 'ASC'));
            if (this.candidateIds) {
                criteria.setIds(this.candidateIds);
            }
            return criteria;
        },
        unavailable(): boolean {
            return (
                !this.loading &&
                this.ids.some((id) => !this.units.some((unit) => unit.id === id && unit.active && unit.organization?.active))
            );
        },
        collection(): EntityCollection<'organization_unit'> {
            const units = this.ids.map((id) => this.units.find((unit) => unit.id === id) ?? this.placeholder(id));
            return new Shopwell.Data.EntityCollection(
                this.repository.route,
                'organization_unit',
                Shopwell.Context.api,
                null,
                units,
            );
        },
    },
    watch: {
        ids: {
            immediate: true,
            handler() {
                void this.load();
            },
        },
        canRead() {
            void this.load();
        },
    },
    beforeUnmount() {
        this.revision += 1;
    },
    methods: {
        placeholder(id: string): Entity<'organization_unit'> {
            const unit = this.repository.create(Shopwell.Context.api, id as Entity<'organization_unit'>['id']);
            unit.name = this.$t('sw-workflow.form.unavailableSelection');
            unit.active = false;
            return unit;
        },
        async load(): Promise<void> {
            const revision = ++this.revision;
            this.units = [];
            this.failed = false;
            this.loading = false;
            if (!this.canRead || !this.ids.length) {
                return;
            }
            this.loading = true;
            try {
                const criteria = new Shopwell.Data.Criteria(1, 200).setIds(this.ids).addAssociation('organization');
                const units = await this.repository.search(criteria, Shopwell.Context.api);
                if (revision === this.revision) {
                    this.units = [...units];
                }
            } catch {
                if (revision === this.revision) {
                    this.failed = true;
                }
            } finally {
                if (revision === this.revision) {
                    this.loading = false;
                }
            }
        },
        eligible(unit: Entity<'organization_unit'>): boolean {
            return (
                unit.active === true &&
                unit.organization?.active === true &&
                (!this.candidateIds || this.candidateIds.includes(unit.id))
            );
        },
        select(id: string | null, unit?: Entity<'organization_unit'>): void {
            if (!this.disabled && this.canRead && id && unit?.id === id && this.eligible(unit)) {
                this.$emit('update:model-value', id);
            }
        },
        change(units: Entity<'organization_unit'>[]): void {
            if (this.disabled || !this.canRead || !Array.isArray(units) || units.length > 200) {
                return;
            }
            if (units.some((unit) => !unit || (!this.ids.includes(unit.id) && !this.eligible(unit)))) {
                return;
            }
            this.$emit('update:model-value', [...new Set(units.map((unit) => unit.id))]);
        },
        clear(): void {
            if (!this.disabled && this.canRead) {
                this.$emit('update:model-value', this.multiple ? [] : '');
            }
        },
    },
});
