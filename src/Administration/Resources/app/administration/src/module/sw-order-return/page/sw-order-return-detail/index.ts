/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-order-return-detail.html.twig';
import './sw-order-return-detail.scss';
import {
    ORDER_RETURN_ACTIONS,
    ORDER_RETURN_TYPES,
    formatReturnAmount,
    returnStateLabelKey,
    returnStateVariant,
    returnTypeLabelKey,
} from '../../service/order-return.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

type OrderReturn = EntitySchema.Entity<'order_return'>;
type OrderReturnMessage = EntitySchema.Entity<'order_return_message'>;
type OrderReturnLineItem = EntitySchema.Entity<'order_return_line_item'>;

/**
 * The three fields of a customer the page needs.
 *
 * The generated entity type of the administration carries the whole customer, but the name is
 * assembled from fields it does not expose on every path - so the page names the shape it reads.
 */
type CustomerName = {
    firstName?: string | null;
    lastName?: string | null;
    email?: string | null;
};

/**
 * One case, with every decision it allows.
 *
 * The page reads the return through the repository and writes it through the action endpoints: a state
 * is never written here, because a decision also restocks goods and starts a refund, and neither of the
 * two is something a form can express.
 *
 * Which button exists is decided twice - by the state of the return and by the role of the operator.
 * The server refuses both wrong cases with a 409 and a 403; the page only keeps the operator from
 * clicking into a refusal.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'orderReturnApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        orderReturnId: {
            type: String,
            required: true,
        },
    },

    data(): {
        orderReturn: OrderReturn | null;
        isLoading: boolean;
        isSubmitting: boolean;
        messageDraft: string;
        showDeclineModal: boolean;
    } {
        return {
            orderReturn: null,
            isLoading: false,
            isSubmitting: false,
            messageDraft: '',
            showDeclineModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.orderReturn?.returnNumber ?? ''),
        };
    },

    computed: {
        orderReturnRepository(): RepositoryType<'order_return'> {
            return this.repositoryFactory.create('order_return');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        stateName(): string | null {
            return this.orderReturn?.stateMachineState?.technicalName ?? null;
        },

        /**
         * The privilege behind every decision. A reader sees the case and nothing else.
         */
        canDecide(): boolean {
            return this.acl.can('order_return.editor');
        },

        /**
         * The decisions the current state allows, read from the one map the service declares - a state
         * that gains an action later only has to change in that map.
         */
        availableActions(): string[] {
            const state = this.stateName;

            if (!state || !this.canDecide) {
                return [];
            }

            return Object.entries(ORDER_RETURN_ACTIONS)
                .filter(([, states]) => states.includes(state))
                .map(([action]) => action);
        },

        /**
         * The name of a return state.
         *
         * The snippet of this module wins over the name the state machine carries: that translation was
         * written for the customer and calls a `requested` return `等待商家处理`, while the page asks the
         * operator to decide. A state the administration does not translate falls back to the name of
         * the machine instead of showing a key.
         */
        stateLabel(): string {
            const state = this.orderReturn?.stateMachineState;

            return this.stateLabelOf(state?.technicalName, state?.translated?.name || state?.name || '–');
        },

        stateVariant(): string {
            return returnStateVariant(this.stateName);
        },

        typeLabel(): string {
            const type = this.orderReturn?.type;

            if (!type) {
                return '–';
            }

            const key = returnTypeLabelKey(type);

            return this.$te(key) ? this.$t(key) : type;
        },

        /**
         * The holder of the case. A guest return has no customer row, only the address it was opened
         * with - the email is the only identity the page can show for it.
         */
        customerLabel(): string {
            const orderReturn = this.orderReturn;

            if (!orderReturn) {
                return '–';
            }

            const customer = orderReturn.customer as unknown as CustomerName | null | undefined;

            if (customer) {
                const name = [customer.firstName, customer.lastName].filter(Boolean).join(' ').trim();

                return name || customer.email || '–';
            }

            return orderReturn.guestEmail || '–';
        },

        /**
         * The reason the customer picked, and the free text as the fallback for a reason that was
         * deleted after the return was opened.
         */
        reasonLabel(): string {
            const orderReturn = this.orderReturn;

            if (!orderReturn) {
                return '–';
            }

            const label = orderReturn.reason?.translated?.label || orderReturn.reason?.label;

            return label || orderReturn.reasonText || '–';
        },

        trackingLabel(): string {
            const orderReturn = this.orderReturn;

            if (!orderReturn?.trackingCode) {
                return '–';
            }

            return orderReturn.trackingCarrier
                ? `${orderReturn.trackingCarrier} · ${orderReturn.trackingCode}`
                : orderReturn.trackingCode;
        },

        isRefundOnly(): boolean {
            return this.orderReturn?.type === ORDER_RETURN_TYPES.REFUND_ONLY;
        },

        /**
         * The columns of the position table. The two flags at the end are the state of the goods, not
         * of the money - which is why they are read-only icons and not inputs.
         */
        lineItemColumns(): Array<Record<string, unknown>> {
            return [
                { property: 'orderLineItemId', label: 'sw-order-return.detail.columnProduct', primary: true },
                { property: 'quantity', label: 'sw-order-return.detail.columnQuantity', align: 'right' },
                { property: 'refundAmount', label: 'sw-order-return.detail.columnRefundAmount', align: 'right' },
                { property: 'accepted', label: 'sw-order-return.detail.columnAccepted', align: 'center' },
                { property: 'restocked', label: 'sw-order-return.detail.columnRestocked', align: 'center' },
            ];
        },

        /**
         * The facts an operator reads first, in the order of the questions they ask: which order,
         * whose case, what kind, how far, how much, until when.
         */
        generalFacts(): Array<{ label: string; value: string }> {
            const orderReturn = this.orderReturn;

            if (!orderReturn) {
                return [];
            }

            return [
                {
                    label: this.$t('sw-order-return.detail.labelOrderNumber'),
                    value: orderReturn.order?.orderNumber || orderReturn.orderId,
                },
                {
                    label: this.$t('sw-order-return.detail.labelCustomer'),
                    value: this.customerLabel,
                },
                {
                    label: this.$t('sw-order-return.detail.labelType'),
                    value: this.typeLabel,
                },
                {
                    label: this.$t('sw-order-return.detail.labelRefundAmount'),
                    value: this.formatAmount(orderReturn.refundAmount, orderReturn.currency),
                },
                {
                    label: this.$t('sw-order-return.detail.labelReason'),
                    value: this.reasonLabel,
                },
                {
                    label: this.$t('sw-order-return.detail.labelCreatedAt'),
                    value: this.formatDateOrDash(orderReturn.createdAt),
                },
                {
                    label: this.$t('sw-order-return.detail.labelDeadline'),
                    value: this.formatDateOrDash(orderReturn.deadlineAt),
                },
                {
                    label: this.$t('sw-order-return.detail.labelTracking'),
                    value: this.trackingLabel,
                },
            ];
        },
    },

    created() {
        void this.loadOrderReturn();
    },

    methods: {
        criteria(): CriteriaType {
            const criteria = new Criteria(1, 1);
            criteria.setIds([this.orderReturnId]);
            criteria.addAssociation('order');
            criteria.addAssociation('customer');
            criteria.addAssociation('reason');
            criteria.addAssociation('stateMachineState');
            criteria.addAssociation('lineItems.orderLineItem');
            criteria.addAssociation('messages');

            return criteria;
        },

        async loadOrderReturn(): Promise<void> {
            this.isLoading = true;

            return this.orderReturnRepository
                .search(this.criteria())
                .then((result) => {
                    this.orderReturn = (result.first() as OrderReturn | null) ?? null;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * Runs one decision and re-reads the row.
         *
         * The endpoints answer with the return in its new shape, but the associations the page renders
         * (line items, messages, state) are the shape of the repository - so the page reads once more
         * instead of merging two shapes.
         *
         * @returns whether the decision was taken
         */
        async runAction(request: () => Promise<unknown>, successSnippet: string): Promise<boolean> {
            if (this.isSubmitting) {
                return false;
            }

            this.isSubmitting = true;

            try {
                await request();

                await this.loadOrderReturn();

                this.createNotificationSuccess({
                    message: this.$t(successSnippet),
                });

                return true;
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error),
                });

                return false;
            } finally {
                this.isSubmitting = false;
            }
        },

        onApprove(): void {
            void this.runAction(
                () => this.orderReturnApiService.approve(this.orderReturnId),
                'sw-order-return.notification.approveSuccess',
            );
        },

        onDecline(reason: string): void {
            this.showDeclineModal = false;

            void this.runAction(
                () => this.orderReturnApiService.decline(this.orderReturnId, reason),
                'sw-order-return.notification.declineSuccess',
            );
        },

        onCancel(): void {
            void this.runAction(
                () => this.orderReturnApiService.cancel(this.orderReturnId, null),
                'sw-order-return.notification.cancelSuccess',
            );
        },

        onReceive(): void {
            void this.runAction(
                () => this.orderReturnApiService.receive(this.orderReturnId),
                'sw-order-return.notification.receiveSuccess',
            );
        },

        onRefund(): void {
            void this.runAction(
                () => this.orderReturnApiService.refund(this.orderReturnId),
                'sw-order-return.notification.refundSuccess',
            );
        },

        onOpenDeclineModal(): void {
            this.showDeclineModal = true;
        },

        onCloseDeclineModal(): void {
            this.showDeclineModal = false;
        },

        /**
         * An internal note, not an answer to the customer: the storefront renders the messages of the
         * customer role only, which is why this text box says so.
         */
        onSendMessage(): void {
            const content = this.messageDraft.trim();

            if (!content) {
                this.createNotificationError({
                    message: this.$t('sw-order-return.detail.messageEmptyError'),
                });

                return;
            }

            void this.runAction(
                () => this.orderReturnApiService.addMessage(this.orderReturnId, content),
                'sw-order-return.notification.messageSuccess',
            ).then((succeeded: boolean) => {
                if (succeeded) {
                    this.messageDraft = '';
                }
            });
        },

        /**
         * A refusal of the server is the only answer that carries a readable reason - the state of the
         * return moved while the page was open, or the order is not refundable anymore. A failed http
         * call holds it under `errors[0].detail`.
         */
        errorMessage(error: unknown): string {
            const response = (error as { response?: { data?: { errors?: Array<{ detail?: string }> } } }).response;
            const detail = response?.data?.errors?.[0]?.detail;

            return detail || this.$t('sw-order-return.notification.actionError');
        },

        stateLabelOf(technicalName: string | null | undefined, fallback: string): string {
            if (!technicalName) {
                return fallback;
            }

            const key = returnStateLabelKey(technicalName);

            return this.$te(key) ? this.$t(key) : fallback;
        },

        formatAmount(amount: number | null, currency: string | null): string {
            return formatReturnAmount(amount, currency);
        },

        /**
         * A date of the API is an ISO string, a date of the entity may already be a `Date` - both are
         * handed to the date filter, which knows how to render either.
         */
        formatDateOrDash(value: unknown): string {
            if (!value) {
                return '–';
            }

            return this.dateFilter(value as string | Date);
        },

        lineItemLabel(lineItem: OrderReturnLineItem): string {
            return lineItem.orderLineItem?.label || lineItem.orderLineItemId;
        },

        /**
         * The customer is a person and a message is an entry - the two kinds must not read the same,
         * which is why the role is translated instead of printed.
         */
        messageAuthor(message: OrderReturnMessage): string {
            const key = `sw-order-return.detail.messageAuthor.${message.authorRole}`;

            return this.$te(key) ? this.$t(key) : message.authorRole;
        },
    },
});
