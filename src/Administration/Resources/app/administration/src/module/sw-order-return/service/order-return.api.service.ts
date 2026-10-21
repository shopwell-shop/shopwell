/**
 * @sw-package after-sales
 *
 * The decisions of the return pages.
 *
 * Reading a return is a plain DAL read and uses the repository. What is not plain is every decision:
 * it moves the state machine, may restock goods and may move money, so the server owns it - the
 * administration never writes a state id.
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

/**
 * The state machine of a return, as it is seeded by the server.
 *
 * @private
 */
export const ORDER_RETURN_STATE_MACHINE = 'order_return.state';

/**
 * The two kinds of a return.
 *
 * `refund_only` is the case where nothing is shipped back - a parcel that never arrived, a price
 * difference, a digital good. `return_refund` is the goods coming back first.
 *
 * @private
 */
export const ORDER_RETURN_TYPES = {
    REFUND_ONLY: 'refund_only',
    RETURN_REFUND: 'return_refund',
} as const;

/**
 * The states in which a decision may still be taken.
 *
 * `requested` waits for the approval, `open` waits for the parcel, `returning` waits for its arrival
 * and `received` waits for the money. A state that is not in this map is final for the operator - the
 * server answers 409, this map only keeps the buttons out of the page.
 *
 * @private
 */
export const ORDER_RETURN_ACTIONS: Record<string, string[]> = {
    approve: ['requested'],
    decline: ['requested'],
    cancel: ['requested', 'open', 'returning'],
    receive: ['returning'],
    refund: ['received', 'returning'],
};

/**
 * A return as the action endpoints present it.
 *
 * The parser flattens the state into `state` and `stateLabel`, so this is not the entity type: reading
 * `response.orderReturn.stateMachineState` would type check against the entity and be `undefined` at
 * runtime.
 *
 * @private
 */
export type OrderReturnView = {
    id: string;
    returnNumber: string;
    orderId: string;
    type: string;
    state: string | null;
    stateLabel: string | null;
    /** The amount in the smallest unit of the currency. */
    refundAmount: number | null;
    currency: string | null;
    reasonId: string | null;
    reasonLabel: string | null;
    reasonText: string | null;
    customerNote: string | null;
    evidence: string[];
    trackingCode: string | null;
    trackingCarrier: string | null;
    createdAt: string | null;
    returnedAt: string | null;
    receivedAt: string | null;
    deadlineAt: string | null;
    lineItems: Array<{
        id: string;
        orderLineItemId: string;
        label: string | null;
        quantity: number;
        refundAmount: number | null;
        accepted: boolean;
        restocked: boolean;
    }>;
    messages: Array<{
        id: string;
        authorRole: string;
        content: string;
        attachments: string[];
        createdAt: string | null;
    }>;
};

type OrderReturnResponse = {
    orderReturn?: OrderReturnView | null;
};

/**
 * The snippet key of the name of a return state.
 *
 * The state machine ships its own translations, and those are the words of the customer
 * (`等待商家处理`). The operator reads a different vocabulary for the same state, so the snippet of this
 * module wins and the name of the machine is only the fallback.
 *
 * @param technicalName e.g. `requested`
 * @private
 */
export function returnStateLabelKey(technicalName: string | null | undefined): string {
    return `sw-order-return.stateLabel.${technicalName}`;
}

/**
 * The variant of the badge that renders a state.
 *
 * `mt-badge` knows five variants and nothing else, so every state is mapped onto one of them instead
 * of letting an unknown name render an unstyled badge. The map reads as the work the state asks for:
 * a decision (`attention`), a parcel (`info`), money (`attention`) or nothing anymore (`positive` /
 * `critical` / `neutral`).
 *
 * @private
 */
export function returnStateVariant(technicalName: string | null | undefined): string {
    switch (technicalName) {
        case 'requested':
            return 'attention';
        case 'open':
        case 'returning':
            return 'info';
        case 'received':
        case 'refunding':
            return 'attention';
        case 'closed':
            return 'positive';
        case 'declined':
            return 'critical';
        case 'canceled':
            return 'neutral';
        default:
            return 'neutral';
    }
}

/**
 * The snippet key of the name of a return type.
 *
 * @private
 */
export function returnTypeLabelKey(type: string | null | undefined): string {
    return `sw-order-return.typeLabel.${type}`;
}

/**
 * Renders an amount the server stores.
 *
 * A return amount is an integer in the smallest unit of its currency - exactly like every other amount
 * of the platform - so it is divided by `10 ** decimals` before it is printed. The decimals come from
 * the system currency, which is the one every amount of a shop is created in; a currency with a
 * different number of decimals can not be mixed into the same shop.
 *
 * @private
 */
export function formatReturnAmount(amount: number | null, currency: string | null): string {
    if (amount === null || amount === undefined) {
        return '–';
    }

    const decimals = systemCurrencyDecimals();

    return Shopwell.Utils.format.currency(amount / 10 ** decimals, currency || 'CNY', decimals);
}

function systemCurrencyDecimals(): number {
    const app = Shopwell.Context.app as { systemCurrency?: { itemRounding?: { decimals?: number } } } | undefined;

    return app?.systemCurrency?.itemRounding?.decimals ?? 2;
}

/**
 * Talks to the decision endpoints of the returns.
 *
 * Every method answers with the return in its new shape, so the page renders the result of a decision
 * without reloading the row - the server already decided what the state, the stock and the refund came
 * to.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class OrderReturnApiService {
    public readonly name = 'orderReturnApiService';

    private readonly httpClient: HttpClient;

    private readonly loginService: LoginService;

    constructor(httpClient: HttpClient, loginService: LoginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    /**
     * Accepts the return. Depending on the type the goods are expected afterwards, or the refund is
     * prepared right away.
     */
    public approve(id: string): Promise<OrderReturnView | null> {
        return this.post(id, 'approve');
    }

    /**
     * Refuses the return. The reason is mandatory - it is the only explanation the customer receives.
     */
    public decline(id: string, reason: string): Promise<OrderReturnView | null> {
        return this.post(id, 'decline', { reason });
    }

    /**
     * Ends the return before it was decided. A note is optional.
     */
    public cancel(id: string, note: string | null): Promise<OrderReturnView | null> {
        return this.post(id, 'cancel', { note });
    }

    /**
     * Books the arrival of the parcel: the goods go back into the stock and the refund is started.
     */
    public receive(id: string): Promise<OrderReturnView | null> {
        return this.post(id, 'receive');
    }

    /**
     * Starts the refund of a return that sits in `received` - the manual way for a parcel that was
     * booked in without a refund, e.g. after a channel was fixed.
     */
    public refund(id: string): Promise<OrderReturnView | null> {
        return this.post(id, 'refund');
    }

    /**
     * Writes an internal note. The customer never sees it - the storefront only renders the messages
     * of the `customer` role.
     */
    public addMessage(id: string, content: string): Promise<OrderReturnView | null> {
        return this.post(id, 'message', { content });
    }

    /**
     * The token of the logged in administrator, as every call of the admin api needs it.
     */
    public getAuthHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }

    private post(id: string, action: string, body: Record<string, unknown> | null = null): Promise<OrderReturnView | null> {
        return this.httpClient
            .post<OrderReturnResponse>(`/_action/order-return/${id}/${action}`, body, {
                headers: this.getAuthHeaders(),
            })
            .then((response) => response.data.orderReturn || null);
    }
}
