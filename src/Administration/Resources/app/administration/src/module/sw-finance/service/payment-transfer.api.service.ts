/**
 * @sw-package framework
 *
 * The two calls of the finance pages that are not plain entity crud: the options of the create modal
 * and the two money movements - the approval and the rejection.
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';

/**
 * What the server answered about a submission.
 *
 * The three values are the whole point of the endpoint: "the channel took it", "the channel refused it"
 * and "the platform does not know" are different facts, and only the middle one may be retried without
 * asking the channel first. `canceled` is the answer of a rejection.
 *
 * @private
 */
export const OUTCOME = {
    ACCEPTED: 'accepted',
    FAILED: 'failed',
    UNKNOWN: 'unknown',
    CANCELED: 'canceled',
} as const;

/**
 * One of the four answers of the submission endpoint.
 *
 * @private
 */
export type TransferOutcome = (typeof OUTCOME)[keyof typeof OUTCOME];

/**
 * The states of a transfer the operator may still decide about.
 *
 * @private
 */
export const DECIDABLE_STATE = 'created';

/**
 * A way of paying a single interface offers, e.g. `alipay_transfer`.
 *
 * @private
 */
export type TransferChannelOption = {
    technicalName: string;
    label: string;
    currencies: string[];
};

/**
 * A payment interface as the server declares it, e.g. `alipay`.
 *
 * The matrix it carries - which identity types it accepts, which fields it demands, from which amount
 * on it wants the holder name - is never re-implemented in the administration.
 *
 * @private
 */
export type TransferProviderOption = {
    technicalName: string;
    /** A snippet key, not a name - see {@see providerLabelKey()}. */
    label: string;
    identityTypes: string[];
    requiredRecipientFields: Record<string, string[]>;
    bankAccountTypes: string[];
    holderNameThreshold: number | null;
    requiresOnboarding: boolean;
    currencies: string[];
    channels: TransferChannelOption[];
};

/**
 * A payment app with the interfaces it may pay through.
 *
 * @private
 */
export type TransferAppOption = {
    id: string;
    technicalName: string;
    name: string;
    type: string;
    isPlatform: boolean;
    providers: TransferProviderOption[];
};

/**
 * An already known recipient of an app.
 *
 * `maskedIdentity` is the only form of the identity the administration ever receives.
 *
 * @private
 */
export type TransferRecipientOption = {
    id: string;
    identityType: string;
    holderName: string | null;
    providerAccountId: string | null;
    maskedIdentity: string | null;
    payoutEnabled: boolean;
    onboardingStatus: string | null;
};

/**
 * The body of a booking. `identityValue` travels exactly once - when a new recipient is created.
 *
 * @private
 */
export type PaymentTransferPayload = {
    paymentAppId: string;
    provider: string;
    channel: string;
    recipientId: string | null;
    identityType: string | null;
    identityValue: string | null;
    holderName: string | null;
    bankInfo: Record<string, string> | null;
    amount: number;
    currency: string;
    outNo: string | null;
    note: string | null;
};

/**
 * The snippet key of the name of a transfer state.
 *
 * The state machine ships its own translations, and those were written for the payment gateway
 * (`打款成功`). The finance pages name the very same state in their own words, so the snippet of this
 * module wins and the name of the machine is only the fallback - see `stateLabelOf()` of the pages.
 *
 * @param technicalName e.g. `succeeded`
 * @private
 */
export function transferStateLabelKey(technicalName: string | null | undefined): string {
    return `sw-finance.transfer.stateLabel.${technicalName}`;
}

/**
 * The snippet key of a payment interface name.
 *
 * `PaymentProvider::$label` is a snippet key and not a name, so the administration has to translate it -
 * keeping the prefix here means the four places that render an interface name can not drift apart.
 *
 * @param technicalName e.g. `alipay`
 * @private
 */
export function providerLabelKey(technicalName: string | null | undefined): string {
    return `sw-settings-payment-provider.provider.${technicalName}`;
}

/**
 * Converts the yuan an operator types into the integer fen the server stores.
 *
 * `parseFloat(value) * 100` is what must never be used here: `1.005 * 100` is `100.49999999999999` in
 * binary floating point and `Math.round` would book one fen too little or too much. The conversion is
 * therefore a string split - the digits the operator typed are the digits that are stored.
 *
 * Zero is not an amount. A payout of nothing is not a payout the platform can send, so the conversion
 * answers `null` for it exactly like it does for a typo: the caller has one question - "is this a
 * payable amount?" - and its answer must not be two different checks in two different files.
 *
 * @param value the amount as typed, e.g. `'12.05'`
 * @returns the amount in the smallest unit, or null when the input is not a positive amount
 * @private
 */
export function yuanToMinorUnits(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const text = String(value).trim();

    // one optional decimal point with one or two digits: `12`, `12.0`, `12.05`. A third digit is a typo
    // and not a rounding problem, so it is refused instead of being rounded away
    if (!/^\d+(\.\d{1,2})?$/.test(text)) {
        return null;
    }

    const [yuan, fraction = ''] = text.split('.');
    const minorUnits = Number(yuan) * 100 + Number(fraction.padEnd(2, '0'));

    if (!Number.isSafeInteger(minorUnits) || minorUnits <= 0) {
        return null;
    }

    return minorUnits;
}

/**
 * The inverse of {@see yuanToMinorUnits()}, for prefilling a form from a stored amount.
 *
 * @private
 */
export function minorUnitsToYuan(minorUnits: number | null | undefined): string {
    if (minorUnits === null || minorUnits === undefined || !Number.isFinite(minorUnits)) {
        return '';
    }

    const sign = minorUnits < 0 ? '-' : '';
    const absolute = Math.abs(Math.trunc(minorUnits));
    const yuan = Math.floor(absolute / 100);
    const fen = String(absolute % 100).padStart(2, '0');

    return `${sign}${yuan}.${fen}`;
}

/**
 * A transfer as the endpoints present it - and **not** the entity.
 *
 * The presenter assembles the answer, so the relations of the row are flattened into `state`, `recipient`
 * and `paymentApp`. That is why this type exists: the entity type was used for these answers, TypeScript
 * accepted `response.transfer.statusMachineState.technicalName` because the entity has that association,
 * and the field was simply `undefined` at runtime - the state of an accepted submission never made it into
 * the notice of the operator.
 *
 * @private
 */
export type PaymentTransferView = {
    id: string;
    transferNo: string;
    outNo: string;
    provider: string;
    channel: string;
    /** The amount in the smallest unit of the currency. */
    amount: number;
    currency: string;
    state: {
        id: string | null;
        technicalName: string | null;
        /** The name the state machine carries, not a snippet - see `transferStateLabelKey()`. */
        label: string | null;
    };
    paymentApp: {
        id: string;
        name: string;
        technicalName: string;
    } | null;
    recipient: {
        id?: string;
        identityType?: string | null;
        holderName?: string | null;
        providerAccountId?: string | null;
        maskedIdentity?: string | null;
        payoutEnabled?: boolean;
        onboardingStatus?: string | null;
    } | null;
    failureCode: string | null;
    failureReason: string | null;
    submitAttempts: number;
    providerRef: string | null;
    providerStageRef: string | null;
    /** The existence only - the payload itself never leaves the server. */
    hasConfirmPayload: boolean;
    createdAt: string | null;
    submittedAt: string | null;
    settledAt: string | null;
    returnedAt: string | null;
    canceledAt: string | null;
    expireAt: string | null;
    metadata: Record<string, unknown> | null;
};

/**
 * The answer of `GET /_action/payment/transfers/options`.
 *
 * @private
 */
export type PaymentTransferOptions = {
    apps: TransferAppOption[];
    defaultAppId: string | null;
};

/**
 * The answer of `POST /_action/payment/transfers`.
 *
 * `replayed` is true when the out number of the request already existed - the same intent, not a
 * second one.
 *
 * @private
 */
export type PaymentTransferBookingResult = {
    transfer: PaymentTransferView | null;
    replayed: boolean;
};

/**
 * The answer of `POST /_action/payment/transfers/{id}/submit`.
 *
 * @private
 */
export type PaymentTransferSubmitResult = {
    outcome: TransferOutcome;
    transfer: PaymentTransferView | null;
    message: string | null;
};

/**
 * The answer of `POST /_action/payment/transfers/{id}/cancel`.
 *
 * @private
 */
export type PaymentTransferCancelResult = {
    outcome: TransferOutcome;
    transfer: PaymentTransferView | null;
};

/**
 * The raw json shapes below exist because a response is json first and a typed object second.
 */
type OptionsResponse = {
    apps?: TransferAppOption[];
    defaultAppId?: string | null;
};

type RecipientsResponse = {
    recipients?: TransferRecipientOption[];
};

type BookingResponse = {
    transfer?: PaymentTransferView | null;
    replayed?: boolean;
};

type SubmitResponse = {
    outcome?: TransferOutcome;
    transfer?: PaymentTransferView | null;
    message?: string | null;
};

type CancelResponse = {
    outcome?: TransferOutcome;
    transfer?: PaymentTransferView | null;
};

/**
 * Talks to the transfer endpoints of the administration.
 *
 * Two rules are visible in every method below and are the reason this class exists instead of the
 * generic repository: the submission of a payout carries **no payload** (the app of a payout is the app
 * of the transfer row, so a request can not name a different one), and a rejection always carries the
 * reason that ends up in the audit trail.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class PaymentTransferApiService {
    public readonly name = 'paymentTransferApiService';

    private readonly httpClient: HttpClient;

    private readonly loginService: LoginService;

    constructor(httpClient: HttpClient, loginService: LoginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    /**
     * The apps, interfaces, channels and identity types the create modal offers.
     *
     * Everything in the answer is a declaration of the server - which identity type an interface
     * accepts, which fields it demands and how long an identity may be are never re-implemented in the
     * administration, they are rendered from here.
     */
    public getOptions(): Promise<PaymentTransferOptions> {
        return this.httpClient
            .get<OptionsResponse>('/_action/payment/transfers/options', { headers: this.getAuthHeaders() })
            .then((response): PaymentTransferOptions => {
                return {
                    apps: response.data.apps || [],
                    defaultAppId: response.data.defaultAppId || null,
                };
            });
    }

    /**
     * The recipients that can already be paid out to in this app and through this interface.
     *
     * The answer carries the masked identity only - the plain value never leaves the server.
     */
    public getRecipients(paymentAppId: string, provider: string): Promise<TransferRecipientOption[]> {
        return this.httpClient
            .get<RecipientsResponse>('/_action/payment/transfers/recipients', {
                headers: this.getAuthHeaders(),
                params: { paymentAppId, provider },
            })
            .then((response) => response.data.recipients || []);
    }

    /**
     * Books the intent to pay. Nothing is sent to a channel by this call.
     */
    public createTransfer(payload: PaymentTransferPayload): Promise<PaymentTransferBookingResult> {
        return this.httpClient
            .post<BookingResponse>('/_action/payment/transfers', payload, { headers: this.getAuthHeaders() })
            .then((response): PaymentTransferBookingResult => {
                return {
                    transfer: response.data.transfer || null,
                    replayed: Boolean(response.data.replayed),
                };
            });
    }

    /**
     * Hands the payout to the channel - the one call that moves money.
     *
     * No body on purpose: the app, the interface, the channel and the recipient are the ones of the
     * transfer row. A request that could name them would make "pay out of the wrong merchant account"
     * a typo away.
     */
    public submitTransfer(id: string): Promise<PaymentTransferSubmitResult> {
        return this.httpClient
            .post<SubmitResponse>(`/_action/payment/transfers/${id}/submit`, null, { headers: this.getAuthHeaders() })
            .then((response): PaymentTransferSubmitResult => {
                return {
                    outcome: response.data.outcome || OUTCOME.UNKNOWN,
                    transfer: response.data.transfer || null,
                    message: response.data.message || null,
                };
            });
    }

    /**
     * Rejects the payout. The reason is mandatory - it is the only audit trail a rejected payout has.
     */
    public cancelTransfer(id: string, reason: string): Promise<PaymentTransferCancelResult> {
        return this.httpClient
            .post<CancelResponse>(`/_action/payment/transfers/${id}/cancel`, { reason }, { headers: this.getAuthHeaders() })
            .then((response): PaymentTransferCancelResult => {
                return {
                    outcome: response.data.outcome || OUTCOME.CANCELED,
                    transfer: response.data.transfer || null,
                };
            });
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
}
