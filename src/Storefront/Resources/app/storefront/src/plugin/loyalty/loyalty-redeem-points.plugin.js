import Plugin from 'src/plugin-system/plugin.class';

/**
 * Handles redeeming loyalty points for the current cart.
 *
 * The available points balance is loaded from the storefront endpoint, the redemption
 * request is sent to the storefront proxy which forwards it to the store-api route and
 * the cart is reloaded afterwards to reflect the new totals.
 *
 * @sw-package checkout
 */
export default class LoyaltyRedeemPointsPlugin extends Plugin {
    static options = {
        infoUrl: '',
        redeemUrl: '',
    };

    init() {
        this._balanceElement = this.el.querySelector('[data-loyalty-redeem-points-balance]');
        this._inputElement = this.el.querySelector('[data-loyalty-redeem-points-input]');
        this._submitButton = this.el.querySelector('[data-loyalty-redeem-points-submit]');

        if (!this._inputElement || !this._submitButton) {
            return;
        }

        this._submitButton.addEventListener('click', this._onSubmit.bind(this));

        this._loadProgram();
    }

    async _loadProgram() {
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

            if (!data.success || !data.loyalty) {
                this._hidePlugin();

                return;
            }

            this._program = data.loyalty;
            this._renderBalance();
        } catch (error) {
            this._hidePlugin();
        }
    }

    _renderBalance() {
        if (!this._balanceElement || !this._program) {
            return;
        }

        this._balanceElement.textContent = String(this._program.pointsBalance ?? 0);
    }

    async _onSubmit() {
        if (!this.options.redeemUrl) {
            return;
        }

        this._submitButton.disabled = true;

        const body = new FormData();
        body.append('points', this._inputElement.value || '0');

        try {
            const response = await fetch(this.options.redeemUrl, {
                method: 'POST',
                body,
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                this.$emitter.publish('loyaltyRedeemPointsSuccess');
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
        this.$emitter.publish('loyaltyRedeemPointsError', errorCode);
    }

    _hidePlugin() {
        this.el.classList.add('d-none');
    }

    _reloadCart() {
        window.location.reload();
    }
}
