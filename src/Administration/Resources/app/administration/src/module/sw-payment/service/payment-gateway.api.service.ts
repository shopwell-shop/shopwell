/**
 * @sw-package framework
 *
 * What every payment page of the administration needs to know about the gateway: the state machines,
 * the words and colours of their states, and the shape of an amount.
 *
 * The gateway stores what the channel reported, so the numbers arrive in the smallest unit of their
 * currency and the states arrive as plain ids. Both are translated here once, for the payment pages
 * and for the payout pages that read the same entities.
 */
import type CriteriaType from 'src/core/data/criteria.data';
import type { HttpRequestConfig } from 'src/core/factory/http-client.types';

const { Criteria } = Shopwell.Data;

/**
 * The state machines the payment entities use. They are separate from `order_transaction.state`, the
 * gateway serves apps that bring their own order.
 *
 * @private
 */
export const PAYMENT_ORDER_STATE_MACHINE = 'payment_order.state';

/**
 * @private
 */
export const PAYMENT_REFUND_STATE_MACHINE = 'payment_refund.state';

/**
 * The machine of the payouts the platform sends to a recipient.
 *
 * @private
 */
export const PAYMENT_TRANSFER_STATE_MACHINE = 'payment_transfer.status';

/**
 * A state of one of those machines, as the entity endpoint delivers it.
 *
 * @private
 */
export interface PaymentState {
    id: string;
    name?: string | null;
    technicalName?: string | null;
    translated?: {
        name?: string | null;
    } | null;
}

/**
 * A payment interface as the settings page renders it: the declaration of its credentials and of its
 * channels, and whether the shop has configured it.
 *
 * @private
 */
export type PaymentProviderView = Record<string, unknown>;

/**
 * The badge variants of `mt-badge`.
 */
const BADGE_VARIANTS = [
    'neutral',
    'info',
    'attention',
    'critical',
    'positive',
];

/**
 * The state of a payment entity is a plain id (it may only be changed through its state machine), so
 * the states of a machine are read once and mapped by id in the administration.
 *
 * @private
 */
export function createPaymentStateCriteria(machine: string): CriteriaType {
    const criteria = new Criteria(1, 50);
    criteria.addFilter(Criteria.equals('stateMachine.technicalName', machine));
    // the machine states have no position of their own, the technical name keeps the list stable
    criteria.addSorting(Criteria.sort('technicalName', 'ASC'));

    return criteria;
}

/**
 * The badge colour of a state, keyed by its technical name. The machines share most of the names
 * (`failed` means the same in all of them), so one map is enough.
 */
const STATE_VARIANTS: Record<string, string> = {
    created: 'neutral',
    opened: 'info',
    paid: 'positive',
    failed: 'critical',
    closed: 'neutral',
    expired: 'attention',
    refunded_partially: 'attention',
    refunded: 'attention',
    open: 'info',
    processing: 'attention',
    finished: 'positive',
    submitted: 'info',
    awaiting_user_confirm: 'attention',
    succeeded: 'positive',
    canceled: 'neutral',
    returned: 'critical',
};

/**
 * The badge colour of a transaction, a webhook delivery or an incoming notification.
 */
const STATUS_VARIANTS: Record<string, string> = {
    pending: 'attention',
    success: 'positive',
    failed: 'critical',
    giving_up: 'critical',
    ok: 'positive',
    duplicate: 'neutral',
    ignored: 'neutral',
    invalid_signature: 'critical',
    unmatched: 'attention',
    amount_mismatch: 'critical',
    error: 'critical',
};

/**
 * A badge variant is only ever one of the five of the component, everything unknown falls back to
 * `neutral` instead of rendering an unstyled badge.
 */
function badgeVariant(variant: string | null | undefined): string {
    return variant && BADGE_VARIANTS.includes(variant) ? variant : 'neutral';
}

/**
 * The state of a machine by id, `null` for an id that is not part of the loaded machine.
 *
 * @private
 */
export function findPaymentState(states: ArrayLike<PaymentState>, stateId: string): PaymentState | null {
    return Array.from(states).find((state) => state.id === stateId) ?? null;
}

/**
 * The name of a state is a translated field, the technical name is the fallback for a state that is
 * not part of the loaded machine.
 *
 * @private
 */
export function findPaymentStateLabel(states: ArrayLike<PaymentState>, stateId: string): string {
    const state = findPaymentState(states, stateId);

    if (!state) {
        return '';
    }

    return state.translated?.name || state.name || state.technicalName || '';
}

/**
 * The badge colour of a state, `neutral` for a state the administration does not know.
 *
 * @private
 */
export function findPaymentStateVariant(states: ArrayLike<PaymentState>, stateId: string): string {
    const state = findPaymentState(states, stateId);

    return badgeVariant((state && STATE_VARIANTS[state.technicalName ?? '']) || 'neutral');
}

/**
 * The badge colour of a status that is not part of a state machine, e.g. the status of a transaction.
 *
 * @private
 */
export function statusVariant(status: string | null | undefined): string {
    return badgeVariant(STATUS_VARIANTS[status ?? ''] || 'neutral');
}

/**
 * The number of fraction digits of a currency, e.g. 2 for CNY and 0 for JPY. The amounts of the
 * gateway are stored in the smallest unit of the currency, so this is what they are divided by.
 *
 * @private
 */
export function getCurrencyFractionDigits(currency: string | null | undefined): number {
    if (!currency) {
        return 2;
    }

    try {
        const { maximumFractionDigits } = new Intl.NumberFormat('en-US', { style: 'currency', currency }).resolvedOptions();

        return maximumFractionDigits ?? 2;
    } catch {
        return 2;
    }
}

/**
 * Formats an amount that is stored in the smallest unit of its currency.
 *
 * @private
 */
export function formatPaymentAmount(amount: number | null | undefined, currency: string): string {
    if (amount === null || amount === undefined) {
        return '';
    }

    const fractionDigits = getCurrencyFractionDigits(currency);

    return Shopwell.Utils.format.currency(amount / 10 ** fractionDigits, currency, fractionDigits);
}

/**
 * Renders a payload of the channel or of an app as the json it is, `null` becomes an empty string so
 * the caller can decide on its own what to show for "no data".
 *
 * @private
 */
export function formatPayload(payload: unknown): string {
    if (payload === null || payload === undefined || typeof payload !== 'object' || Object.keys(payload).length === 0) {
        return '';
    }

    try {
        return JSON.stringify(payload, null, 2);
    } catch {
        return '';
    }
}

/**
 * The transport methods this service calls, narrowed to the envelope it reads: only `data` of a
 * response is used, so a caller - in particular a test - does not have to provide the whole client
 * with its interceptors and its generic response type.
 *
 * @private
 */
export type PaymentHttpClient = {
    get: <T>(url: string, config?: HttpRequestConfig) => Promise<{ data: T }>;
    post: <T>(url: string, payload?: unknown, config?: HttpRequestConfig) => Promise<{ data: T }>;
    put: <T>(url: string, payload?: unknown, config?: HttpRequestConfig) => Promise<{ data: T }>;
    delete: <T>(url: string, config?: HttpRequestConfig) => Promise<{ data: T }>;
};

/**
 * The one call this service makes on the login service: the token of the current session, which is
 * sent as the bearer of every request.
 *
 * @private
 */
export type PaymentTokenProvider = {
    getToken: () => string;
};

/**
 * Talks to the payment endpoints of the administration.
 *
 * @private
 */
export default class PaymentGatewayService {
    public readonly name = 'paymentGatewayService';

    private readonly httpClient: PaymentHttpClient;

    private readonly loginService: PaymentTokenProvider;

    constructor(httpClient: PaymentHttpClient, loginService: PaymentTokenProvider) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    /**
     * The payment interfaces the shop can be configured with. An interface is code and lives in the
     * server, the administration only renders it, so the list is asked for instead of being hard
     * coded here.
     *
     * Every interface carries the declaration of its credentials and of its channels, which is what
     * the settings page renders.
     */
    public listProviders(): Promise<PaymentProviderView[]> {
        return this.httpClient
            .get<{ providers?: PaymentProviderView[] }>('/_action/payment/providers', { headers: this.getAuthHeaders() })
            .then((response) => response.data.providers ?? []);
    }

    /**
     * Stores the credentials of a payment interface. Only the values that are sent are replaced, an
     * empty one keeps what the server already has.
     */
    public saveProvider(provider: string, payload: Record<string, unknown>): Promise<PaymentProviderView | null> {
        return this.httpClient
            .post<{ provider?: PaymentProviderView | null }>(`/_action/payment/providers/${provider}`, payload, {
                headers: this.getAuthHeaders(),
            })
            .then((response) => response.data.provider ?? null);
    }

    /**
     * Removes the credentials of a payment interface, the interface becomes unconfigured again.
     */
    public resetProvider(provider: string): Promise<PaymentProviderView | null> {
        return this.httpClient
            .delete<{ provider?: PaymentProviderView | null }>(`/_action/payment/providers/${provider}`, {
                headers: this.getAuthHeaders(),
            })
            .then((response) => response.data.provider ?? null);
    }

    /**
     * Stores the platform parameters of one channel of a payment interface, e.g. the appid of a
     * wechat channel. They belong to the channel alone, the credentials belong to the interface.
     */
    public saveProviderChannel(
        provider: string,
        channel: string,
        payload: Record<string, unknown>,
    ): Promise<PaymentProviderView | null> {
        return this.httpClient
            .post<{
                channel?: PaymentProviderView | null;
            }>(`/_action/payment/providers/${provider}/channels/${channel}`, payload, { headers: this.getAuthHeaders() })
            .then((response) => response.data.channel ?? null);
    }

    /**
     * Removes the platform parameters of one channel of a payment interface.
     */
    public resetProviderChannel(provider: string, channel: string): Promise<PaymentProviderView | null> {
        return this.httpClient
            .delete<{
                channel?: PaymentProviderView | null;
            }>(`/_action/payment/providers/${provider}/channels/${channel}`, { headers: this.getAuthHeaders() })
            .then((response) => response.data.channel ?? null);
    }

    /**
     * The apps that are allowed to call the payment api. The secrets never leave the server, so an
     * app only tells whether it has one.
     */
    public listApps(): Promise<Array<Record<string, unknown>>> {
        return this.httpClient
            .get<{ apps?: Array<Record<string, unknown>> }>('/_action/payment/apps', { headers: this.getAuthHeaders() })
            .then((response) => response.data.apps ?? []);
    }

    /**
     * Creates or updates an app. An app that is created without a secret gets a generated one, which
     * is part of the response and never readable again.
     */
    public saveApp(app: Record<string, unknown>): Promise<Record<string, unknown>> {
        return this.httpClient
            .post<Record<string, unknown>>('/_action/payment/apps', app, { headers: this.getAuthHeaders() })
            .then((response) => response.data);
    }

    public deleteApp(id: string): Promise<void> {
        return this.httpClient
            .delete(`/_action/payment/apps/${id}`, { headers: this.getAuthHeaders() })
            .then(() => undefined);
    }

    /**
     * The gateway channel a payment method collects through.
     *
     * The answer carries four things because the page needs all of them to render one card: the
     * binding itself (`null` when the method is not bound yet), the account that is charged - the tenant
     * of the platform when the method is not bound yet -, whether the method currently dispatches on the
     * gateway handler at all, and the pairs other payment methods already use.
     */
    public getPaymentMethodChannel(paymentMethodId: string): Promise<Record<string, unknown>> {
        return this.httpClient
            .get<Record<string, unknown>>(`/_action/payment/methods/${paymentMethodId}/channel`, {
                headers: this.getAuthHeaders(),
            })
            .then((response) => response.data);
    }

    /**
     * Binds the payment method to the channel, or replaces the channel it collected through so far.
     *
     * One call and not two: the binding row and the handler of the payment method only make sense
     * together, and the handler is not writable through the generic entity endpoints.
     *
     * The account may be left out: the tenant of the platform is the account the shop's own checkout
     * collects on, so an installation with a single tenant sends nothing.
     */
    public savePaymentMethodChannel(
        paymentMethodId: string,
        channelTechnicalName: string,
        paymentAppId: string | null = null,
    ): Promise<Record<string, unknown>> {
        const payload: { channelTechnicalName: string; paymentAppId?: string } = { channelTechnicalName };

        if (paymentAppId) {
            payload.paymentAppId = paymentAppId;
        }

        return this.httpClient
            .put<Record<string, unknown>>(`/_action/payment/methods/${paymentMethodId}/channel`, payload, {
                headers: this.getAuthHeaders(),
            })
            .then((response) => response.data);
    }

    public getAuthHeaders(): Record<string, string> {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
