/**
 * @sw-package framework
 */

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
 * @private
 */
export function createPaymentStateCriteria(machine) {
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
const STATE_VARIANTS = {
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
const STATUS_VARIANTS = {
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
function badgeVariant(variant) {
    return BADGE_VARIANTS.includes(variant) ? variant : 'neutral';
}

/**
 * @returns {Object|null}
 * @private
 */
export function findPaymentState(states, stateId) {
    return states.find((item) => item.id === stateId) || null;
}

/**
 * The name of a state is a translated field, the technical name is the fallback for a state that is
 * not part of the loaded machine.
 * @private
 */
export function findPaymentStateLabel(states, stateId) {
    const state = findPaymentState(states, stateId);

    if (!state) {
        return '';
    }

    return state.translated?.name || state.name || state.technicalName || '';
}

/**
 * The badge colour of a state, `neutral` for a state the administration does not know.
 * @private
 */
export function findPaymentStateVariant(states, stateId) {
    const state = findPaymentState(states, stateId);

    return badgeVariant((state && STATE_VARIANTS[state.technicalName]) || 'neutral');
}

/**
 * The badge colour of a status that is not part of a state machine, e.g. the status of a transaction.
 * @private
 */
export function statusVariant(status) {
    return badgeVariant(STATUS_VARIANTS[status] || 'neutral');
}

/**
 * The number of fraction digits of a currency, e.g. 2 for CNY and 0 for JPY. The amounts of the
 * gateway are stored in the smallest unit of the currency, so this is what they are divided by.
 * @private
 */
export function getCurrencyFractionDigits(currency) {
    if (!currency) {
        return 2;
    }

    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits;
    } catch {
        return 2;
    }
}

/**
 * Formats an amount that is stored in the smallest unit of its currency.
 * @private
 */
export function formatPaymentAmount(amount, currency) {
    if (amount === null || amount === undefined) {
        return '';
    }

    const fractionDigits = getCurrencyFractionDigits(currency);

    return Shopwell.Utils.format.currency(amount / 10 ** fractionDigits, currency, fractionDigits);
}

/**
 * Renders a payload of the channel or of an app as the json it is, `null` becomes an empty string so
 * the caller can decide on its own what to show for "no data".
 * @private
 */
export function formatPayload(payload) {
    if (payload === null || payload === undefined || Object.keys(payload).length === 0) {
        return '';
    }

    try {
        return JSON.stringify(payload, null, 2);
    } catch {
        return '';
    }
}

/**
 * Talks to the payment endpoints of the administration.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class PaymentGatewayService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'paymentGatewayService';
    }

    /**
     * The payment interfaces the shop can be configured with. An interface is code and lives in the
     * server, the administration only renders it, so the list is asked for instead of being hard
     * coded here.
     *
     * Every interface carries the declaration of its credentials and of its channels, which is what
     * the settings page renders.
     *
     * @returns {Promise<Array>}
     */
    listProviders() {
        return this.httpClient
            .get('/_action/payment/providers', { headers: this.getAuthHeaders() })
            .then((response) => response.data.providers || []);
    }

    /**
     * Stores the credentials of a payment interface. Only the values that are sent are replaced, an
     * empty one keeps what the server already has.
     *
     * @returns {Promise<Object>}
     */
    saveProvider(provider, payload) {
        return this.httpClient
            .post(`/_action/payment/providers/${provider}`, payload, { headers: this.getAuthHeaders() })
            .then((response) => response.data.provider || null);
    }

    /**
     * Removes the credentials of a payment interface, the interface becomes unconfigured again.
     *
     * @returns {Promise<Object>}
     */
    resetProvider(provider) {
        return this.httpClient
            .delete(`/_action/payment/providers/${provider}`, { headers: this.getAuthHeaders() })
            .then((response) => response.data.provider || null);
    }

    /**
     * Stores the platform parameters of one channel of a payment interface, e.g. the appid of a
     * wechat channel. They belong to the channel alone, the credentials belong to the interface.
     *
     * @returns {Promise<Object>}
     */
    saveProviderChannel(provider, channel, payload) {
        return this.httpClient
            .post(`/_action/payment/providers/${provider}/channels/${channel}`, payload, { headers: this.getAuthHeaders() })
            .then((response) => response.data.channel || null);
    }

    /**
     * Removes the platform parameters of one channel of a payment interface.
     *
     * @returns {Promise<Object>}
     */
    resetProviderChannel(provider, channel) {
        return this.httpClient
            .delete(`/_action/payment/providers/${provider}/channels/${channel}`, { headers: this.getAuthHeaders() })
            .then((response) => response.data.channel || null);
    }

    /**
     * The apps that are allowed to call the payment api. The secrets never leave the server, so an
     * app only tells whether it has one.
     *
     * @returns {Promise<Array>}
     */
    listApps() {
        return this.httpClient
            .get('/_action/payment/apps', { headers: this.getAuthHeaders() })
            .then((response) => response.data.apps || []);
    }

    /**
     * Creates or updates an app. An app that is created without a secret gets a generated one, which
     * is part of the response and never readable again.
     *
     * @returns {Promise<Object>}
     */
    saveApp(app) {
        return this.httpClient
            .post('/_action/payment/apps', app, { headers: this.getAuthHeaders() })
            .then((response) => response.data);
    }

    /**
     * @returns {Promise<void>}
     */
    deleteApp(id) {
        return this.httpClient.delete(`/_action/payment/apps/${id}`, { headers: this.getAuthHeaders() });
    }

    /**
     * The gateway channel a payment method collects through.
     *
     * The answer carries four things because the page needs all of them to render one card: the
     * binding itself (`null` when the method is not bound yet), the account that is charged - the tenant
     * of the platform when the method is not bound yet -, whether the method currently dispatches on the
     * gateway handler at all, and the pairs other payment methods already use.
     *
     * @returns {Promise<Object>}
     */
    getPaymentMethodChannel(paymentMethodId) {
        return this.httpClient
            .get(`/_action/payment/methods/${paymentMethodId}/channel`, { headers: this.getAuthHeaders() })
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
     *
     * @returns {Promise<Object>}
     */
    savePaymentMethodChannel(paymentMethodId, channelTechnicalName, paymentAppId = null) {
        const payload = { channelTechnicalName };

        if (paymentAppId) {
            payload.paymentAppId = paymentAppId;
        }

        return this.httpClient
            .put(`/_action/payment/methods/${paymentMethodId}/channel`, payload, { headers: this.getAuthHeaders() })
            .then((response) => response.data);
    }

    getAuthHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
