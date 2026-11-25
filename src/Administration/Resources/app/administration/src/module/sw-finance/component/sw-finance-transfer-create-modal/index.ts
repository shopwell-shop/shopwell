/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-finance-transfer-create-modal.html.twig';
import './sw-finance-transfer-create-modal.scss';
import { fromMinorUnits, providerLabelKey, toMinorUnits } from '../../service/payment-transfer.api.service';
import type {
    PaymentTransferPayload,
    TransferReapplicationDraft,
    PaymentTransferBookingResult,
    TransferAccountOption,
    TransferAppOption,
    TransferRecipientOption,
    TransferChannelOption,
} from '../../service/payment-transfer.api.service';

const { Mixin } = Shopwell;

/**
 * The identity value is stored in an encrypted column of 149 **bytes** - a multibyte identity runs out of
 * room long before it runs out of characters, so the check has to measure bytes as well.
 */
const IDENTITY_VALUE_MAX_BYTES = 149;

/**
 * Counts the UTF-8 bytes of a string by hand instead of going through `TextEncoder`, which is not
 * available in every test environment and would leave the limit unverifiable.
 */
function utf8ByteLength(value: string): number {
    let bytes = 0;

    for (const character of value) {
        const codePoint = character.codePointAt(0) ?? 0;

        if (codePoint <= 0x7f) {
            bytes += 1;
        } else if (codePoint <= 0x7ff) {
            bytes += 2;
        } else if (codePoint <= 0xffff) {
            bytes += 3;
        } else {
            bytes += 4;
        }
    }

    return bytes;
}

/**
 * The out number is the idempotency key and every interface caps it at 30 characters.
 */
const OUT_NO_PATTERN = /^[0-9a-zA-Z]{1,30}$/;

const NOTE_MAX_LENGTH = 255;
const HOLDER_NAME_MAX_LENGTH = 255;

type BankInfo = {
    account_type: string;
    inst_name?: string;
    inst_branch_name?: string;
};

/**
 * Books a transfer request - the first of the two steps, the one that moves **no** money.
 *
 * Everything the form offers is a declaration of the server (`/options`): which interfaces an app may
 * pay through, which channels each interface offers, which identity types a payout accepts and which
 * fields it insists on. The matrix is deliberately not re-implemented here - a second truth in the
 * browser would silently diverge from the one the server validates against.
 *
 * The one conversion the form owns is the typed amount to the smallest unit of the selected currency,
 * and it is a string split (`toMinorUnits`), never a float multiplication.
 */
/** @private */
export default defineComponent({
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['paymentTransferApiService'],

    emits: ['modal-close', 'transfer-created'],

    props: {
        initialDraft: { type: Object as PropType<TransferReapplicationDraft | null>, default: null },
        sourceTransferId: { type: String, default: null },
        fixedAmount: { type: String, default: null },
        fixedCurrency: { type: String, default: null },
        purpose: { type: String, default: '' },
        bookTransfer: {
            type: Function as PropType<(payload: PaymentTransferPayload) => Promise<PaymentTransferBookingResult>>,
            default: null,
        },
    },

    data(): {
        isApplyingInitialDraft: boolean;
        selectionUnavailable: boolean;
        recipientGeneration: number;
        isLoadingOptions: boolean;
        workflowAvailable: boolean;
        approvalFlowActive: boolean;
        previewBlocked: boolean;
        isSaving: boolean;
        isLoadingRecipients: boolean;
        apps: TransferAppOption[];
        recipients: TransferRecipientOption[];
        paymentAppId: string | null;
        accountId: string | null;
        channel: string | null;
        recipientSource: 'existing' | 'new';
        recipientId: string | null;
        identityType: string | null;
        identityValue: string;
        holderName: string;
        bankAccountType: string | null;
        bankName: string;
        bankBranchName: string;
        amount: string;
        currency: string;
        outNo: string;
        note: string;
        errors: Record<string, boolean>;
    } {
        return {
            isApplyingInitialDraft: false,
            selectionUnavailable: false,
            recipientGeneration: 0,
            isLoadingOptions: true,
            workflowAvailable: false,
            approvalFlowActive: false,
            previewBlocked: false,
            isSaving: false,
            isLoadingRecipients: false,
            apps: [],
            recipients: [],
            paymentAppId: null,
            accountId: null,
            channel: null,
            // an existing account is the common case: a payout usually goes to somebody who was paid before
            recipientSource: 'existing',
            recipientId: null,
            identityType: null,
            identityValue: '',
            holderName: '',
            bankAccountType: null,
            bankName: '',
            bankBranchName: '',
            amount: this.fixedAmount ?? '',
            currency: this.fixedCurrency ?? '',
            outNo: this.sourceTransferId ? Shopwell.Utils.createId().slice(0, 30) : '',
            note: '',
            errors: {},
        };
    },

    computed: {
        selectedApp(): TransferAppOption | null {
            return this.apps.find((app) => app.id === this.paymentAppId) ?? null;
        },

        accountOptions(): TransferAccountOption[] {
            return this.selectedApp?.accounts ?? [];
        },

        selectedAccount(): TransferAccountOption | null {
            return this.accountOptions.find((option) => option.accountId === this.accountId) ?? null;
        },

        provider(): string | null {
            return this.selectedAccount?.technicalName ?? null;
        },

        channelOptions(): TransferChannelOption[] {
            return this.selectedAccount?.channels ?? [];
        },

        /**
         * A channel that offers exactly one way of paying is chosen for the operator - a dropdown with a
         * single entry is a step that only invites mistakes.
         */
        isChannelLocked(): boolean {
            return this.channelOptions.length === 1 && this.channelOptions[0].technicalName === this.channel;
        },

        identityTypeOptions(): string[] {
            return this.selectedAccount?.identityTypes ?? [];
        },

        bankAccountTypes(): string[] {
            return this.selectedAccount?.bankAccountTypes ?? [];
        },

        /**
         * The fields the interface demands for this very identity type. `bankInfo` and `holderName` are
         * the two the form can satisfy, everything else is refused by the server with a readable 422.
         */
        requiredRecipientFields(): string[] {
            if (this.identityType === null) {
                return [];
            }

            return this.selectedAccount?.requiredRecipientFields[this.identityType] ?? [];
        },

        /**
         * wechat is the only interface whose name requirement depends on the amount: from 2000 yuan on
         * the name of the holder has to travel with the payout.
         */
        requiresHolderName(): boolean {
            if (this.requiredRecipientFields.includes('holderName')) {
                return true;
            }

            const threshold = this.selectedAccount?.holderNameThreshold;
            const amount = this.amountInMinorUnits;

            return threshold !== null && threshold !== undefined && amount !== null && amount >= threshold;
        },

        requiresBankInfo(): boolean {
            return this.requiredRecipientFields.includes('bankInfo') || this.identityType === 'bankcard';
        },

        /**
         * The currencies the interface settles in. The server already resolved "the rules of the
         * interface win over the currencies of the channel", so this is the union to offer - and an empty
         * list means "not restricted".
         */
        currencyOptions(): string[] {
            const declared = this.selectedAccount?.currencies ?? [];

            if (declared.length > 0) {
                return declared;
            }

            const channelCurrencies =
                this.channelOptions.find((option) => option.technicalName === this.channel)?.currencies ?? [];

            if (channelCurrencies.length > 0) {
                return channelCurrencies;
            }

            return this.currency === '' ? [] : [this.currency];
        },

        isCurrencyLocked(): boolean {
            return (
                this.fixedCurrency !== null ||
                (this.currencyOptions.length === 1 && this.currencyOptions.includes(this.currency))
            );
        },

        payableRecipients(): TransferRecipientOption[] {
            return this.recipients.filter((recipient) => recipient.payoutEnabled);
        },

        hasPayableRecipients(): boolean {
            return this.payableRecipients.length > 0;
        },

        /**
         * The amount the booking is sent with, converted with the exponent of the currency the operator
         * picked - a payout in JPY has no minor unit, so a fixed "yuan times a hundred" would book a
         * hundred times the amount. `toMinorUnits()` already refuses zero and the shape of a non
         * amount, so this is the stored form of a payable input and nothing else.
         */
        amountInMinorUnits(): number | null {
            return toMinorUnits(this.amount, this.currency);
        },

        isIdentityValueTooLong(): boolean {
            return utf8ByteLength(this.identityValue) > IDENTITY_VALUE_MAX_BYTES;
        },

        isOutNoInvalid(): boolean {
            return this.outNo !== '' && !OUT_NO_PATTERN.test(this.outNo);
        },

        isNoteTooLong(): boolean {
            return this.note.length > NOTE_MAX_LENGTH;
        },

        isHolderNameTooLong(): boolean {
            return this.holderName.length > HOLDER_NAME_MAX_LENGTH;
        },

        isRecipientIncomplete(): boolean {
            if (this.recipientSource === 'existing') {
                return this.recipientId === null;
            }

            return (
                this.identityType === null ||
                this.identityValue.trim() === '' ||
                this.isIdentityValueTooLong ||
                this.isHolderNameTooLong ||
                (this.requiresHolderName && this.holderName.trim() === '') ||
                (this.requiresBankInfo && this.bankAccountType === null)
            );
        },

        /**
         * The same conditions the server enforces, evaluated in the browser so the operator learns about
         * a mistake before a round trip - the server stays the authority.
         */
        isSaveDisabled(): boolean {
            return (
                this.isSaving ||
                this.isLoadingOptions ||
                this.isLoadingRecipients ||
                (!this.bookTransfer && (!this.workflowAvailable || this.previewBlocked)) ||
                this.isFormIncomplete
            );
        },

        approvalPreviewPayload(): PaymentTransferPayload | null {
            return this.isLoadingOptions || this.isFormIncomplete ? null : this.buildPayload();
        },

        isFormIncomplete(): boolean {
            if (this.paymentAppId === null || this.provider === null || this.channel === null) {
                return true;
            }

            if (this.isRecipientIncomplete) {
                return true;
            }

            return (
                this.amountInMinorUnits === null ||
                this.currency === '' ||
                !this.currencyOptions.includes(this.currency) ||
                this.isOutNoInvalid ||
                this.isNoteTooLong
            );
        },
    },

    watch: {
        /**
         * Switching the app switches the merchant account, and a recipient belongs to one account only.
         * Keeping any downstream selection would book a payout to an account the new app does not know -
         * which is the one mistake of this form that moves money across tenants.
         */
        paymentAppId(): void {
            if (this.isApplyingInitialDraft) return;
            this.accountId = null;
            this.applyDefaultsOfApp();
        },

        accountId(): void {
            if (this.isApplyingInitialDraft) return;
            this.resetDownstreamOfAccount();
        },

        channel(): void {
            if (this.isApplyingInitialDraft) return;
            this.recipientId = null;
            this.identityType = null;
            this.identityValue = '';
            this.holderName = '';
            this.bankAccountType = null;
            this.bankName = '';
            this.bankBranchName = '';

            void this.loadRecipients();
        },

        recipientSource(): void {
            if (this.isApplyingInitialDraft) return;
            this.recipientId = null;
            this.identityType = null;
            this.identityValue = '';
            this.holderName = '';
            this.bankAccountType = null;
            this.bankName = '';
            this.bankBranchName = '';
        },

        identityType(): void {
            this.bankAccountType = this.requiresBankInfo ? (this.bankAccountTypes[0] ?? '1') : null;
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            await this.loadOptions();
        },

        async loadOptions(): Promise<void> {
            this.isLoadingOptions = true;

            try {
                const options = await this.paymentTransferApiService.getOptions();

                this.workflowAvailable = options.workflowAvailable;
                this.approvalFlowActive = options.approvalFlowActive === true && !this.sourceTransferId;
                this.apps = options.apps;
                if (this.initialDraft) {
                    await this.applyInitialDraft(this.initialDraft);
                } else {
                    this.paymentAppId = options.defaultAppId ?? options.apps[0]?.id ?? null;
                    this.applyDefaultsOfApp();
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-finance.errors.loadFailed'),
                });
            } finally {
                this.isLoadingOptions = false;
            }
        },

        async applyInitialDraft(draft: TransferReapplicationDraft): Promise<void> {
            this.isApplyingInitialDraft = true;
            try {
                this.amount = fromMinorUnits(draft.amount, draft.currency);
                this.note = draft.note ?? '';
                this.currency = draft.currency;
                this.paymentAppId = this.apps.some((app) => app.id === draft.paymentAppId) ? draft.paymentAppId : null;
                this.accountId = this.accountOptions.some((account) => account.accountId === draft.accountId)
                    ? draft.accountId
                    : null;
                this.channel = this.channelOptions.some((channel) => channel.technicalName === draft.channel)
                    ? draft.channel
                    : null;
                await this.loadRecipients();
                this.recipientId = this.payableRecipients.some((recipient) => recipient.id === draft.recipientId)
                    ? draft.recipientId
                    : null;
                if (!this.currencyOptions.includes(this.currency)) this.currency = '';
                this.selectionUnavailable =
                    !this.paymentAppId || !this.accountId || !this.channel || !this.recipientId || !this.currency;
                await this.$nextTick();
            } finally {
                this.isApplyingInitialDraft = false;
            }
        },

        /**
         * Preselects the first interface and - when there is only one - its channel. Everything the
         * operator does not have to decide is decided here, everything else stays empty.
         */
        applyDefaultsOfApp(): void {
            const provider = this.accountOptions[0] ?? null;

            this.accountId = provider?.accountId ?? null;

            if (provider === null) {
                this.channel = null;
                this.recipients = [];

                return;
            }

            this.channel = provider.channels.length === 1 ? provider.channels[0].technicalName : null;
        },

        resetDownstreamOfAccount(): void {
            const channels = this.channelOptions;

            this.channel = channels.length === 1 ? channels[0].technicalName : null;
            this.recipientId = null;
            this.identityType = null;
            this.identityValue = '';
            this.holderName = '';
            this.bankAccountType = null;
            this.bankName = '';
            this.bankBranchName = '';

            const currencies = this.currencyOptions;

            if (this.fixedCurrency === null && currencies.length > 0 && !currencies.includes(this.currency)) {
                this.currency = currencies[0];
            }

            void this.loadRecipients();
        },

        async loadRecipients(): Promise<void> {
            const generation = ++this.recipientGeneration;
            if (this.paymentAppId === null || this.provider === null) {
                this.isLoadingRecipients = false;
                this.recipients = [];

                return;
            }

            this.isLoadingRecipients = true;

            try {
                const recipients = await this.paymentTransferApiService.getRecipients(this.paymentAppId, this.provider);
                if (generation === this.recipientGeneration) this.recipients = recipients;
            } catch {
                // a failed lookup only empties the select - the operator can still register a new account,
                // so this is not worth an error notice
                if (generation === this.recipientGeneration) this.recipients = [];
            } finally {
                if (generation === this.recipientGeneration) this.isLoadingRecipients = false;
            }
        },

        /**
         * The interface label is a snippet key declared by the server.
         */
        optionLabel(label: string | null, technicalName: string): string {
            if (!label) {
                return technicalName;
            }

            return this.$te(label) ? this.$t(label) : technicalName;
        },

        accountOptionLabel(option: TransferAccountOption): string {
            const key = providerLabelKey(option.technicalName);

            if (this.$te(key)) {
                return `${option.name} (${this.$t(key)})`;
            }

            return `${option.name} (${this.optionLabel(option.label, option.technicalName)})`;
        },

        identityTypeLabel(identityType: string): string {
            const key = `sw-finance.transfer.create.identityType.${identityType}`;

            return this.$te(key) ? this.$t(key) : identityType;
        },

        /**
         * A recipient is recognised by what a human knows about it: the name, the identity type and the
         * masked handle. The plain identity never leaves the server, so it can not be shown here.
         */
        recipientOptionLabel(recipient: TransferRecipientOption): string {
            const parts = [
                recipient.holderName || null,
                this.identityTypeLabel(recipient.identityType),
                recipient.maskedIdentity || recipient.providerAccountId || null,
            ].filter(Boolean);

            return parts.length > 0 ? parts.join(' · ') : recipient.id;
        },

        bankInfo(): BankInfo | null {
            if (!this.requiresBankInfo || this.bankAccountType === null) {
                return null;
            }

            const bankInfo: BankInfo = { account_type: this.bankAccountType };

            if (this.bankName.trim() !== '') {
                bankInfo.inst_name = this.bankName.trim();
            }

            if (this.bankBranchName.trim() !== '') {
                bankInfo.inst_branch_name = this.bankBranchName.trim();
            }

            return bankInfo;
        },

        /**
         * The payload of the endpoint. `recipientId` and the identity fields are mutually exclusive: the
         * server ignores the identity of an existing recipient, sending both would only hide a mistake.
         */
        buildPayload(): PaymentTransferPayload {
            const usesExistingRecipient = this.recipientSource === 'existing';

            // every value below is enforced by `isSaveDisabled`, so a caller that gets here has them -
            // the empty fallbacks exist for the type checker, not for the server
            const paymentAppId = this.paymentAppId ?? '';
            const provider = this.provider ?? '';
            const channel = this.channel ?? '';
            const amount = this.amountInMinorUnits ?? 0;

            return {
                paymentAppId,
                accountId: this.accountId ?? '',
                provider,
                channel,
                recipientId: usesExistingRecipient ? this.recipientId : null,
                identityType: usesExistingRecipient ? null : this.identityType,
                identityValue: usesExistingRecipient ? null : this.identityValue.trim(),
                holderName: usesExistingRecipient ? null : this.holderName.trim() || null,
                bankInfo: usesExistingRecipient ? null : this.bankInfo(),
                // the amount the operator typed, converted by digit and not by float
                amount,
                currency: this.currency.toUpperCase(),
                outNo: this.outNo.trim() || null,
                note: this.note.trim() || null,
            };
        },

        onSave(): void {
            if (this.isSaveDisabled) {
                return;
            }

            this.isSaving = true;

            const booking = this.bookTransfer
                ? this.bookTransfer(this.buildPayload())
                : this.sourceTransferId
                  ? this.paymentTransferApiService.reapplyTransfer(this.sourceTransferId, this.buildPayload())
                  : this.paymentTransferApiService.createTransfer(this.buildPayload());

            booking
                .then((result) => {
                    this.createNotificationSuccess({
                        message: this.$t(
                            result.replayed
                                ? 'sw-finance.transfer.create.messageReplayed'
                                : 'sw-finance.transfer.create.messageCreated',
                        ),
                    });

                    this.$emit('transfer-created', result.transfer);
                })
                .catch((error: { response?: { data?: { errors?: Array<{ detail?: string }> } } }) => {
                    const detail = error.response?.data?.errors?.[0]?.detail;

                    this.createNotificationError({
                        message: detail || this.$t('sw-finance.transfer.create.messageFailed'),
                    });
                })
                .finally(() => {
                    this.isSaving = false;
                });
        },

        /**
         * A save in flight must not be aborted by a stray click on the backdrop: the request may already
         * have booked a payout.
         */
        onClose(): void {
            if (this.isSaving) {
                return;
            }

            this.$emit('modal-close');
        },
    },
});
