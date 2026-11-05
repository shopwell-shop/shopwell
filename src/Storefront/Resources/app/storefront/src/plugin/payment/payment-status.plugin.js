import Plugin from 'src/plugin-system/plugin.class';
import PaymentPoller from 'src/plugin/payment/payment-handover';

/**
 * Waits for server-confirmed settlement before returning a QR checkout to its payment handler.
 *
 * @sw-package checkout
 */
export default class PaymentStatusPlugin extends Plugin {
    static options = {
        statusUrl: '',
        pollInterval: 4000,
        pollTimeout: 600000,
        pendingMessage: '',
        paidMessage: '',
        closedMessage: '',
        failedMessage: '',
    };

    init() {
        this._message = this.el.querySelector('[data-payment-status-message]');
        this._qr = this.el.querySelector('[data-payment-status-qr]');
        this._returnLink = this.el.querySelector('[data-payment-status-return]');
        this._check = this.el.querySelector('[data-payment-status-check]');
        this._poller = new PaymentPoller({
            probe: () => this._fetchStatus(),
            onState: (payment) => this._onState(payment),
            interval: this.options.pollInterval,
            timeout: this.options.pollTimeout,
        });
        this._onCheck = () => this._poller.start();
        this._onLeave = () => this._poller.stop();
        this._onReturn = (event) => {
            if (event.persisted) {
                this._poller.start();
            }
        };
        this._check?.addEventListener('click', this._onCheck);
        window.addEventListener('pagehide', this._onLeave);
        window.addEventListener('pageshow', this._onReturn);
        this._poller.start();
    }

    destroy() {
        this._poller.stop();
        this._check?.removeEventListener('click', this._onCheck);
        window.removeEventListener('pagehide', this._onLeave);
        window.removeEventListener('pageshow', this._onReturn);
        super.destroy();
    }

    async _fetchStatus() {
        try {
            const response = await fetch(this.options.statusUrl, { cache: 'no-store' });

            return response.ok ? await response.json() : null;
        } catch {
            return null;
        }
    }

    _onState(payment) {
        const state = ['paid', 'closed', 'failed'].includes(payment.state) ? payment.state : 'pending';
        const complete = state !== 'pending';
        if (this._message) {
            this._message.textContent = this.options[`${state}Message`];
        }
        this._qr?.classList.toggle('d-none', complete);
        if (state === 'paid') {
            this._returnLink?.click();
        }

        return complete;
    }
}
