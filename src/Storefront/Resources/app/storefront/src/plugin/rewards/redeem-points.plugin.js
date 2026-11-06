import Plugin from 'src/plugin-system/plugin.class';

/**
 * Redeems the customer's reward points for the current cart.
 *
 * The balance is read from the account endpoint, the redemption is posted to the storefront proxy that
 * forwards it to the store-api route, and the cart is reloaded afterwards to show the new totals.
 *
 * The `data-loyalty-redeem-points-*` attributes and the include path of the component stay as they are:
 * they are the surface a theme overrides, while this file is ours to move. The programme is called
 * "rewards" in the account area and stays "loyalty" in the code that predates it - the names describe
 * who owns the file, not which page renders it.
 *
 * @sw-package checkout
 */
export default class RedeemPointsPlugin extends Plugin {
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

        this._loadRewards();
    }

    async _loadRewards() {
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

            if (!data.success || !data.rewards) {
                this._hidePlugin();

                return;
            }

            this._rewards = data.rewards;
            this._renderBalance();
        } catch (error) {
            this._hidePlugin();
        }
    }

    _renderBalance() {
        if (!this._balanceElement || !this._rewards) {
            return;
        }

        this._balanceElement.textContent = String(this._rewards.pointsBalance ?? 0);
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
                this.$emitter.publish('rewardsRedeemSuccess');
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
        this.$emitter.publish('rewardsRedeemError', errorCode);
    }

    _hidePlugin() {
        this.el.classList.add('d-none');
    }

    _reloadCart() {
        window.location.reload();
    }
}
