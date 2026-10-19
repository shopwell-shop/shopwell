import Plugin from 'src/plugin-system/plugin.class';
import PaymentPoller, {
    FLOW_QRCODE,
    FLOW_REDIRECT,
    FLOW_SDK,
    canPayWithSdk,
    payWithSdk,
    renderQrCode,
    resolveHandover,
    submitHtmlForm,
} from 'src/plugin/payment/payment-handover';

/**
 * Buys a membership plan from the account page.
 *
 * The plan is only named by its id: the price, the tier and the duration are read again on the server
 * when the order is written, so a page that stayed open while the operator changed the price cannot
 * buy at the old one. What comes back is only how the payer is handed over to the channel - the same
 * handover the recharge uses - and the order is then watched until the server says it was activated.
 *
 * @sw-package checkout
 */
export default class MembershipPurchasePlugin extends Plugin {
    static options = {
        purchaseUrl: '',
        statusUrl: '',
        returnUrl: '',
        qrHintTemplate: '',
        pollInterval: 4000,
        pollTimeout: 600000,
    };

    init() {
        this._buttons = this.el.querySelectorAll('[data-membership-plan-id]');
        this._channelSelect = this.el.querySelector('[data-membership-purchase-channel]');
        this._feedback = this.el.querySelector('[data-membership-purchase-feedback]');
        this._message = this.el.querySelector('[data-membership-purchase-message]');
        this._qrBox = this.el.querySelector('[data-membership-purchase-qrcode]');
        this._qrTarget = this.el.querySelector('[data-membership-purchase-qrcode-target]');
        this._qrHint = this.el.querySelector('[data-membership-purchase-qrcode-hint]');
        this._checkButton = this.el.querySelector('[data-membership-purchase-check]');

        this._poller = null;
        this._orderId = null;
        this._activeButton = null;

        if (this._buttons.length === 0) {
            return;
        }

        this._buttons.forEach((button) => {
            button.addEventListener('click', () => this._purchase(button));
        });

        this._checkButton?.addEventListener('click', () => this._watch(this._orderId));
    }

    async _purchase(button) {
        const planId = button.dataset.membershipPlanId;

        if (!planId) {
            return;
        }

        this._stopWatching();
        this._hideQrCode();
        this._setBusy(button, true);
        this._activeButton = button;
        this._showMessage(this._text('loyaltyMembershipProcessing'), 'info');

        const body = new FormData();
        body.append('planId', planId);
        body.append('channel', this._channelSelect?.value ?? '');
        body.append('returnUrl', this.options.returnUrl || window.location.href);

        try {
            const response = await fetch(this.options.purchaseUrl, { method: 'POST', body });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success || !data.order) {
                this._setBusy(button, false);
                this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger');
                this._publishError(data.errorCode ?? null);

                return;
            }

            this._handOver(data.order, button);
        } catch (error) {
            this._setBusy(button, false);
            this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger');
            this._publishError(null);
        }
    }

    /**
     * @param {{id: string, amount: number, currency: string, params: object}} order
     * @param {HTMLElement} button
     */
    _handOver(order, button) {
        const handover = resolveHandover(order);

        if (handover.kind === FLOW_REDIRECT && handover.htmlForm) {
            if (!submitHtmlForm(handover.htmlForm)) {
                this._setBusy(button, false);
                this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger');
            }

            return;
        }

        if (handover.kind === FLOW_REDIRECT && handover.payUrl) {
            window.location.assign(handover.payUrl);

            return;
        }

        if (handover.kind === FLOW_QRCODE) {
            this._showQrCode(order, handover.qrContent, button);

            return;
        }

        if (handover.kind === FLOW_SDK) {
            this._payWithSdk(order, handover.sdkParams, button);

            return;
        }

        this._setBusy(button, false);
        this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger');
        this._publishError('unsupported-flow');
    }

    _payWithSdk(order, sdkParams, button) {
        if (!canPayWithSdk(sdkParams)) {
            this._setBusy(button, false);
            this._showMessage(this._text('loyaltyMembershipSdkUnsupported'), 'danger');

            return;
        }

        payWithSdk(sdkParams, (result) => {
            if (result === 'ok') {
                this._watch(order.id);

                return;
            }

            this._setBusy(button, false);
            this._showMessage(this._text('loyaltyMembershipOpen'), 'warning');
        });
    }

    _showQrCode(order, qrContent, button) {
        if (!this._qrBox || !this._qrTarget) {
            this._setBusy(button, false);
            this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger');

            return;
        }

        if (this._qrHint) {
            this._qrHint.textContent = this._text('qrHintTemplate').replace('%channel%', this._selectedChannelLabel());
        }

        renderQrCode(this._qrTarget, qrContent)
            .catch(() => this._showMessage(this._text('loyaltyMembershipErrorGeneric'), 'danger'))
            .finally(() => this._setBusy(button, false));

        this._qrBox.classList.remove('d-none');
        this._hideMessage();
        this._watch(order.id);
    }

    _watch(orderId) {
        if (!orderId) {
            return;
        }

        this._orderId = orderId;
        this._stopWatching();

        this._poller = new PaymentPoller({
            interval: Number(this.options.pollInterval),
            timeout: Number(this.options.pollTimeout),
            probe: () => this._fetchOrder(orderId),
            onState: (order) => this._onState(order),
        });

        this._poller.start();
    }

    _onState(order) {
        if (order.state === 'activated') {
            this._showMessage(this._text('loyaltyMembershipActivated'), 'success');
            this.$emitter.publish('membershipPurchaseSuccess');
            window.setTimeout(() => window.location.reload(), 1200);

            return true;
        }

        if (order.state === 'failed') {
            this._showMessage(this._text('loyaltyMembershipFailed'), 'danger');
            this._publishError('membership-order-failed');

            return true;
        }

        this._showMessage(this._text('loyaltyMembershipOpen'), 'info');

        return false;
    }

    /**
     * The membership page already answers the recent orders of the customer, so the state of the
     * order that was just opened is read from there instead of adding a second endpoint.
     */
    async _fetchOrder(orderId) {
        try {
            const response = await fetch(this.options.statusUrl, { method: 'GET' });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success || !Array.isArray(data.loyalty?.membershipOrders)) {
                return null;
            }

            return data.loyalty.membershipOrders.find((order) => order.id === orderId) ?? null;
        } catch (error) {
            return null;
        }
    }

    _stopWatching() {
        this._poller?.stop();
        this._poller = null;
    }

    _selectedChannelLabel() {
        if (!this._channelSelect) {
            return '';
        }

        const option = this._channelSelect.options[this._channelSelect.selectedIndex];

        return option ? option.textContent.trim() : '';
    }

    _setBusy(button, busy) {
        this._buttons.forEach((entry) => {
            entry.disabled = busy;
        });

        if (!busy && button) {
            button.disabled = false;
        }
    }

    _showMessage(text, type) {
        if (!this._feedback || !this._message) {
            return;
        }

        this._message.textContent = text;
        this._feedback.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');
        this._feedback.classList.add(`alert-${type}`);
    }

    _hideMessage() {
        this._feedback?.classList.add('d-none');
    }

    _hideQrCode() {
        this._qrBox?.classList.add('d-none');

        if (this._qrTarget) {
            this._qrTarget.innerHTML = '';
        }
    }

    _publishError(errorCode) {
        this.$emitter.publish('membershipPurchaseError', errorCode);
    }

    _text(key) {
        return this.options[key] ?? '';
    }
}
