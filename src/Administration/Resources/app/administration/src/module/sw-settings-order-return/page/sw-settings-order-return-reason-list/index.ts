/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-order-return-reason-list.html.twig';
import './sw-settings-order-return-reason-list.scss';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type OrderReturnReason = EntitySchema.Entity<'order_return_reason'>;

/**
 * The reasons a customer may pick when opening a return.
 *
 * The list is not shipped by the core: which reasons fit depends on what a shop sells, so the core
 * only brings the mechanism and four neutral entries to start with. A reason that is switched off
 * stays on the returns that already picked it - the label is read through the association, never copied
 * into the return.
 *
 * Editing happens in a modal and not inline: the label is translated, so a row carries one label per
 * language and an inline cell would always silently write the current one.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        reasons: EntityCollection<'order_return_reason'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        showModal: boolean;
        reasonToEdit: OrderReturnReason | null;
    } {
        return {
            reasons: null,
            isLoading: false,
            sortBy: 'position',
            sortDirection: 'ASC',
            showModal: false,
            reasonToEdit: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        reasonRepository(): RepositoryType<'order_return_reason'> {
            return this.repositoryFactory.create('order_return_reason');
        },

        canEdit(): boolean {
            return this.acl.can('order_return.editor');
        },

        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'position', this.sortDirection, this.naturalSorting));

            return criteria;
        },

        columns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'position',
                    label: 'sw-settings-order-return.reason.columnPosition',
                    width: '100px',
                },
                {
                    property: 'label',
                    label: 'sw-settings-order-return.reason.columnLabel',
                    primary: true,
                },
                {
                    property: 'active',
                    label: 'sw-settings-order-return.reason.columnActive',
                    width: '120px',
                },
            ];
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            return this.reasonRepository
                .search(this.criteria)
                .then((result) => {
                    this.total = result.total ?? 0;
                    this.reasons = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): void {
            void this.getList();
        },

        onCreate(): void {
            this.reasonToEdit = null;
            this.showModal = true;
        },

        onEdit(reason: OrderReturnReason): void {
            this.reasonToEdit = reason;
            this.showModal = true;
        },

        onCloseModal(): void {
            this.showModal = false;
            this.reasonToEdit = null;
        },

        onSaved(): void {
            this.showModal = false;
            this.reasonToEdit = null;

            void this.getList();
        },
    },
});
