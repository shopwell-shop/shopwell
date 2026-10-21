/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-order-detail-return.html.twig';
import './sw-order-detail-return.scss';
import {
    ORDER_RETURN_TYPES,
    formatReturnAmount,
    returnStateLabelKey,
    returnStateVariant,
    returnTypeLabelKey,
} from '../../service/order-return.api.service';
import type { OrderReturnPosition } from '../../service/order-return.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type OrderReturn = EntitySchema.Entity<'order_return'>;
type StateMachineState = EntitySchema.Entity<'state_machine_state'>;

/**
 * A position of the order, as the form of a new return shows it.
 *
 * The quantity is the number the operator types, `selected` is whether the position takes part at all -
 * a return of three positions should not need three filled in forms, so the checkbox is what puts a
 * position into the request.
 */
type DraftPosition = OrderReturnPosition & {
    selected: boolean;
    draftQuantity: number;
};

/**
 * The returns of one order, on the tab of the order detail page.
 *
 * The page exists for the question an operator has while the customer is on the phone - "what is
 * running on this order, and what can still go back?" - and it answers it without leaving the order:
 *
 *  1. **The list is scoped to the order.** The queue under the returns menu is sorted by deadline and
 *     spans the shop; this list answers about one order, so it is ordered by arrival.
 *  2. **A return can be opened here.** The operator names positions and a type, the server computes the
 *     amount and starts the case in `requested` - the decision about it is still a decision, exactly as
 *     if the customer had opened it in the storefront.
 *  3. **What can not go back is not offered.** The positions come from the server, which subtracts what
 *     running returns of the same order already claimed.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'orderReturnApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        orderId: {
            type: String,
            required: true,
        },
    },

    data(): {
        orderReturns: EntityCollection<'order_return'> | null;
        isLoading: boolean;
        isSubmitting: boolean;
        showCreateModal: boolean;
        isLoadingForm: boolean;
        orderNumber: string | null;
        formCurrency: string | null;
        formDecimals: number;
        reasons: Array<{ id: string; label: string }>;
        draftPositions: DraftPosition[];
        draftType: string;
        draftReasonId: string | null;
        draftNote: string;
    } {
        return {
            orderReturns: null,
            isLoading: false,
            isSubmitting: false,
            showCreateModal: false,
            isLoadingForm: false,
            orderNumber: null,
            formCurrency: null,
            formDecimals: 2,
            reasons: [],
            draftPositions: [],
            draftType: ORDER_RETURN_TYPES.RETURN_REFUND,
            draftReasonId: null,
            draftNote: '',
        };
    },

    computed: {
        orderReturnRepository(): RepositoryType<'order_return'> {
            return this.repositoryFactory.create('order_return');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        /**
         * Opening a return writes a case, which is a decision of the same role that decides about it. A
         * reader sees the list and no button.
         */
        canEdit(): boolean {
            return this.acl.can('order_return.editor');
        },

        columns(): Array<Record<string, unknown>> {
            return [
                { property: 'returnNumber', label: 'sw-order-return.orderTab.columnReturnNumber', allowResize: true },
                { property: 'type', label: 'sw-order-return.orderTab.columnType', allowResize: true },
                { property: 'stateMachineState.technicalName', label: 'sw-order-return.orderTab.columnState', allowResize: true },
                { property: 'refundAmount', label: 'sw-order-return.orderTab.columnRefundAmount', align: 'right', allowResize: true },
                { property: 'deadlineAt', label: 'sw-order-return.orderTab.columnDeadline', allowResize: true },
                { property: 'createdAt', label: 'sw-order-return.orderTab.columnCreatedAt', allowResize: true },
            ];
        },

        typeOptions(): Array<{ value: string; label: string }> {
            return Object.values(ORDER_RETURN_TYPES).map((type) => {
                return { value: type, label: this.typeLabel(type) };
            });
        },

        reasonOptions(): Array<{ value: string; label: string }> {
            return this.reasons.map((reason) => {
                return { value: reason.id, label: reason.label };
            });
        },

        selectedPositions(): DraftPosition[] {
            return this.draftPositions.filter((position) => position.selected && position.draftQuantity > 0);
        },

        /**
         * A return without a position is nothing the server would accept, so the button waits for one.
         * The amount is not asked for, it is computed from the positions by the server.
         */
        canSubmit(): boolean {
            return !this.isSubmitting && this.selectedPositions.length > 0;
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        createdComponent(): Promise<void> {
            return this.loadOrderReturns();
        },

        /**
         * The returns of this order only: the tab answers a question about one order and must not show
         * the cases of another one.
         */
        loadOrderReturns(): Promise<void> {
            this.isLoading = true;

            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('orderId', this.orderId));
            criteria.addAssociation('stateMachineState');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return this.orderReturnRepository
                .search(criteria)
                .then((searchResult) => {
                    this.orderReturns = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        async onOpenCreateModal(): Promise<void> {
            this.draftType = ORDER_RETURN_TYPES.RETURN_REFUND;
            this.draftReasonId = null;
            this.draftNote = '';
            this.draftPositions = [];

            this.showCreateModal = true;
            this.isLoadingForm = true;

            try {
                const formData = await this.orderReturnApiService.formData(this.orderId);

                this.orderNumber = formData.orderNumber;
                this.formCurrency = formData.currency;
                this.formDecimals = formData.decimals;
                this.reasons = formData.reasons;
                // the reason is the vocabulary a return is opened with, so the first one the shop
                // arranged is preselected - an empty select would only add a click
                this.draftReasonId = formData.reasons[0]?.id ?? null;
                this.draftPositions = formData.positions.map((position) => {
                    return { ...position, selected: false, draftQuantity: position.returnable };
                });

                this.showCreateModal = true;
            } catch (error) {
                this.showCreateModal = false;

                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isLoadingForm = false;
            }
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
        },

        onSubmit(): void {
            if (!this.canSubmit) {
                return;
            }

            this.isSubmitting = true;

            const draft = {
                type: this.draftType,
                positions: this.selectedPositions.map((position) => {
                    return { lineItemId: position.lineItemId, quantity: position.draftQuantity };
                }),
                reasonId: this.draftReasonId,
                customerNote: this.draftNote.trim() === '' ? null : this.draftNote.trim(),
            };

            this.orderReturnApiService
                .create(this.orderId, draft)
                .then(async (orderReturn) => {
                    this.showCreateModal = false;

                    await this.loadOrderReturns();

                    this.createNotificationSuccess({
                        message: this.$t('sw-order-return.orderTab.messageCreated', { returnNumber: orderReturn?.returnNumber }),
                    });
                })
                .catch((error: unknown) => {
                    this.createNotificationError({ message: this.errorMessage(error) });
                })
                .finally(() => {
                    this.isSubmitting = false;
                });
        },

        stateName(item: OrderReturn): string | null {
            const state = item.stateMachineState as StateMachineState | null | undefined;

            return state?.technicalName ?? null;
        },

        /**
         * The name of a return state: the snippet of this module wins over the translation the state
         * machine carries, because that one was written for the customer.
         */
        stateLabel(item: OrderReturn): string {
            const state = item.stateMachineState as StateMachineState | null | undefined;
            const technicalName = state?.technicalName ?? null;

            if (!technicalName) {
                return '–';
            }

            const key = returnStateLabelKey(technicalName);

            return this.$te(key) ? this.$t(key) : state?.translated?.name || state?.name || technicalName;
        },

        stateVariant(item: OrderReturn): string {
            return returnStateVariant(this.stateName(item));
        },

        typeLabel(type: string | null | undefined): string {
            if (!type) {
                return '–';
            }

            const key = returnTypeLabelKey(type);

            return this.$te(key) ? this.$t(key) : type;
        },

        formatAmount(amount: number | null, currency: string | null): string {
            return formatReturnAmount(amount, currency);
        },

        /**
         * The price of one unit as the order stores it - a plain number, not the smallest unit - so it
         * is rendered with the currency and the decimals of this order instead of being divided.
         */
        formatUnitPrice(unitPrice: number): string {
            return Shopwell.Utils.format.currency(unitPrice, this.formCurrency || 'CNY', this.formDecimals);
        },

        formatDateOrDash(value: unknown): string {
            if (!value) {
                return '–';
            }

            return this.dateFilter(value as string | Date);
        },

        /**
         * A deadline that has passed is the one number on this page an operator must not miss, so it is
         * rendered as overdue rather than as a date.
         */
        deadlineLabel(item: OrderReturn): string {
            if (!item.deadlineAt) {
                return '–';
            }

            const deadline = new Date(item.deadlineAt as string | Date).getTime();

            if (Number.isNaN(deadline)) {
                return this.formatDateOrDash(item.deadlineAt);
            }

            return deadline < Date.now() ? this.$t('sw-order-return.orderTab.textOverdue') : this.dateFilter(item.deadlineAt as string | Date);
        },

        isOverdue(item: OrderReturn): boolean {
            if (!item.deadlineAt) {
                return false;
            }

            const deadline = new Date(item.deadlineAt as string | Date).getTime();

            return !Number.isNaN(deadline) && deadline < Date.now();
        },

        /**
         * The server refuses with a reason a person can read (`errors[0].detail`) - the message of the
         * http layer would only be the status code.
         */
        errorMessage(error: unknown): string {
            const response = (error as { response?: { data?: { errors?: Array<{ detail?: string }> } } }).response;
            const detail = response?.data?.errors?.[0]?.detail;

            return detail || this.$t('sw-order-return.notification.actionError');
        },
    },
});
