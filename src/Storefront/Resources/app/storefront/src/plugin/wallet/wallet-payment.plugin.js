import Plugin from 'src/plugin-system/plugin.class';

/**
 * Handles paying a part of the current cart with the customer wallet.
 *
 * The available balance for the active currency is loaded from the storefront endpoint,
 * the payment request is sent to the storefront proxy which forwards it to the store-api
 * route and the cart is reloaded afterwards to reflect the new totals.
 *
 * @sw-package checkout
 */
export default class WalletPaymentPlugin extends Plugin {
    static options = {
        infoUrl: '',
        paymentUrl: '',
        currencyId: null,
    };

    init() {
        this._balanceElement = this.el.querySelector('[data-wallet-payment-balance]');
        this._inputElement = this.el.querySelector('[data-wallet-payment-input]');
        this._submitButton = this.el.querySelector('[data-wallet-payment-submit]');

        if (!this._inputElement || !this._submitButton) {
            return;
        }

        this._submitButton.addEventListener('click', this._onSubmit.bind(this));

        this._loadWallet();
    }

    async _loadWallet() {
        if (!this.options.infoUrl) {
            this._hidePlugin();

            return;
        }

        try {
            const response = await fetch(this.options.infoUrl, { method: 'GET' });

            if (!response.ok) {
                this._hidePlugin();

                return;
            }

            const data = await response.json();

            if (!data.success || !data.wallet) {
                this._hidePlugin();

                return;
            }

            this._wallet = data.wallet;
            this._renderBalance();
        } catch (error) {
            this._hidePlugin();
        }
    }

    _renderBalance() {
        if (!this._balanceElement || !this._wallet || !Array.isArray(this._wallet.wallets)) {
            return;
        }

        const wallet = this._wallet.wallets.find((entry) => entry.currencyId === this.options.currencyId);

        this._balanceElement.textContent = String(wallet ? wallet.balance : 0);
    }

    async _onSubmit() {
        if (!this.options.paymentUrl) {
            return;
        }

        this._submitButton.disabled = true;

        const body = new FormData();
        body.append('amount', this._inputElement.value || '0');

        try {
            const response = await fetch(this.options.paymentUrl, {
                method: 'POST',
                body,
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                this.$emitter.publish('walletPaymentSuccess');
                this._reloadCart();

                return;
            }

            this._showError(data.errorCode ?? null);
        } catch (error) {
            this._showError(null);
        }

        this._submitButton.disabled = false;
    }

    _showError(errorCode) {
        this.$emitter.publish('walletPaymentError', errorCode);
    }

    _hidePlugin() {
        this.el.classList.add('d-none');
    }

    _reloadCart() {
        window.location.reload();
    }
}
