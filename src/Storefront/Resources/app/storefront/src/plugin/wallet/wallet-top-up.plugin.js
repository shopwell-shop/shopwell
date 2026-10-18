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
 * Recharges the customer wallet.
 *
 * The plugin owns no payment logic: it asks the storefront endpoint for a top-up and then only does
 * what the channel asked for - send the browser to a url, submit a form, render a scanned code or
 * hand the parameters to the WeChat bridge. Everything the money does afterwards happens on the
 * server, so the page never guesses whether a payment went through: it polls the top-up until the
 * server says it was credited.
 *
 * @sw-package checkout
 */
export default class WalletTopUpPlugin extends Plugin {
    static options = {
        topUpUrl: '',
        statusUrl: '',
        returnUrl: '',
        currency: 'CNY',
        min: 0,
        max: 0,
        presets: [],
        qrHintTemplate: '',
        pollInterval: 4000,
        pollTimeout: 600000,
    };

    init() {
        this._form = this.el.querySelector('[data-wallet-top-up-form]');
        this._amountInput = this.el.querySelector('[data-wallet-top-up-amount]');
        this._channelSelect = this.el.querySelector('[data-wallet-top-up-channel]');
        this._submitButton = this.el.querySelector('[data-wallet-top-up-submit]');
        this._feedback = this.el.querySelector('[data-wallet-top-up-feedback]');
        this._message = this.el.querySelector('[data-wallet-top-up-message]');
        this._qrBox = this.el.querySelector('[data-wallet-top-up-qrcode]');
        this._qrTarget = this.el.querySelector('[data-wallet-top-up-qrcode-target]');
        this._qrHint = this.el.querySelector('[data-wallet-top-up-qrcode-hint]');
        this._qrAmount = this.el.querySelector('[data-wallet-top-up-qrcode-amount]');
        this._checkButton = this.el.querySelector('[data-wallet-top-up-check]');

        this._poller = null;
        this._topUpId = null;

        if (!this._form || !this._amountInput || !this._channelSelect) {
            return;
        }

        this._form.addEventListener('submit', this._onSubmit.bind(this));
        this._checkButton?.addEventListener('click', () => this._watch(this._topUpId));

        this.el.querySelectorAll('[data-wallet-top-up-preset]').forEach((button) => {
            button.addEventListener('click', this._onPreset.bind(this));
        });

        // the recent top-ups sit next to the form, so their buttons are looked up once the page is up
        document.querySelectorAll('[data-wallet-top-up-status]').forEach((button) => {
            button.addEventListener('click', () => this._watch(button.dataset.walletTopUpStatus));
        });

        document.querySelectorAll('[data-wallet-top-up-resume]').forEach((button) => {
            button.addEventListener('click', () => this._onResume(button.dataset.walletTopUpResume));
        });
    }

    _onPreset(event) {
        this._amountInput.value = event.currentTarget.dataset.walletTopUpPreset;
        this._amountInput.focus();
    }

    _onResume(amount) {
        this._amountInput.value = amount;
        this._amountInput.focus();
        this.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        this._hideMessage();
    }

    async _onSubmit(event) {
        event.preventDefault();

        const amount = Number.parseFloat(this._amountInput.value);
        const error = this._validate(amount);

        if (error !== null) {
            this._showMessage(error, 'danger');

            return;
        }

        this._stopWatching();
        this._hideQrCode();
        this._setBusy(true);
        this._showMessage(this._text('walletTopUpProcessing'), 'info');

        const body = new FormData();
        body.append('amount', String(amount));
        body.append('channel', this._channelSelect.value);
        body.append('returnUrl', this.options.returnUrl || window.location.href);

        try {
            const response = await fetch(this.options.topUpUrl, { method: 'POST', body });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success || !data.topUp) {
                this._setBusy(false);
                this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger');
                this._publishError(data.errorCode ?? null);

                return;
            }

            this._handOver(data.topUp);
        } catch (error) {
            this._setBusy(false);
            this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger');
            this._publishError(null);
        }
    }

    /**
     * @param {{id: string, amount: number, currency: string, channel: string, params: object}} topUp
     */
    _handOver(topUp) {
        const handover = resolveHandover(topUp);

        if (handover.kind === FLOW_REDIRECT && handover.htmlForm) {
            if (!submitHtmlForm(handover.htmlForm)) {
                this._setBusy(false);
                this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger');
            }

            return;
        }

        if (handover.kind === FLOW_REDIRECT && handover.payUrl) {
            window.location.assign(handover.payUrl);

            return;
        }

        if (handover.kind === FLOW_QRCODE) {
            this._showQrCode(topUp, handover.qrContent);

            return;
        }

        if (handover.kind === FLOW_SDK) {
            this._payWithSdk(topUp, handover.sdkParams);

            return;
        }

        this._setBusy(false);
        this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger');
        this._publishError('unsupported-flow');
    }

    _payWithSdk(topUp, sdkParams) {
        if (!canPayWithSdk(sdkParams)) {
            this._setBusy(false);
            this._showMessage(this._text('walletTopUpSdkUnsupported'), 'danger');

            return;
        }

        payWithSdk(sdkParams, (result) => {
            if (result === 'ok') {
                this._watch(topUp.id);

                return;
            }

            this._setBusy(false);
            this._showMessage(this._text('walletTopUpOpen'), 'warning');
        });
    }

    _showQrCode(topUp, qrContent) {
        if (!this._qrBox || !this._qrTarget) {
            this._setBusy(false);
            this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger');

            return;
        }

        if (this._qrHint) {
            this._qrHint.textContent = this._text('qrHintTemplate').replace('%channel%', this._selectedChannelLabel());
        }

        if (this._qrAmount) {
            this._qrAmount.textContent = this._formatAmount(topUp.amount, topUp.currency);
        }

        renderQrCode(this._qrTarget, qrContent)
            .catch(() => this._showMessage(this._text('walletTopUpErrorGeneric'), 'danger'))
            .finally(() => this._setBusy(false));

        this._qrBox.classList.remove('d-none');
        this._hideMessage();
        this._watch(topUp.id);
    }

    /**
     * Watches one top-up until it is credited or failed.
     *
     * The check button of an old attempt goes through the very same path, so a payment that was
     * confirmed while the page was open does not need a manual reload to show up.
     */
    _watch(topUpId) {
        if (!topUpId) {
            return;
        }

        this._topUpId = topUpId;
        this._stopWatching();

        this._poller = new PaymentPoller({
            interval: Number(this.options.pollInterval),
            timeout: Number(this.options.pollTimeout),
            probe: () => this._fetchStatus(topUpId),
            onState: (topUp) => this._onState(topUp),
        });

        this._poller.start();
    }

    _onState(topUp) {
        if (topUp.state === 'credited') {
            this._showMessage(this._text('walletTopUpPaid'), 'success');
            this.$emitter.publish('walletTopUpSuccess');
            window.setTimeout(() => window.location.reload(), 1200);

            return true;
        }

        if (topUp.state === 'failed') {
            this._showMessage(this._text('walletTopUpFailed'), 'danger');
            this._publishError('top-up-failed');

            return true;
        }

        this._showMessage(
            topUp.state === 'open' ? this._text('walletTopUpOpen') : this._text('walletTopUpStatusPending'),
            'info',
        );

        return false;
    }

    async _fetchStatus(topUpId) {
        try {
            const response = await fetch(this.options.statusUrl.replace('__TOP_UP_ID__', encodeURIComponent(topUpId)), {
                method: 'GET',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success || !data.topUp) {
                return null;
            }

            return data.topUp;
        } catch (error) {
            return null;
        }
    }

    _stopWatching() {
        this._poller?.stop();
        this._poller = null;
    }

    _validate(amount) {
        if (Number.isNaN(amount) || amount <= 0) {
            return this._text('walletTopUpErrorAmount');
        }

        const min = Number(this.options.min || 0);

        if (min > 0 && amount < min) {
            return this._text('walletTopUpErrorAmount');
        }

        const max = Number(this.options.max || 0);

        if (max > 0 && amount > max) {
            return this._text('walletTopUpErrorAmount');
        }

        return null;
    }

    _selectedChannelLabel() {
        const option = this._channelSelect.options[this._channelSelect.selectedIndex];

        return option ? option.textContent.trim() : '';
    }

    _formatAmount(amount, currency) {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || undefined, {
                style: 'currency',
                currency: currency || this.options.currency,
            }).format(amount);
        } catch (error) {
            return `${amount} ${currency ?? ''}`.trim();
        }
    }

    _setBusy(busy) {
        if (this._submitButton) {
            this._submitButton.disabled = busy;
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
        this.$emitter.publish('walletTopUpError', errorCode);
    }

    /**
     * The texts are handed in by the template, so nothing is hard coded here and every shop can
     * translate them in its own snippet set.
     */
    _text(key) {
        return this.options[key] ?? '';
    }
}
