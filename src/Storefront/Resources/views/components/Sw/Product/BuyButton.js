export default class BuyButton extends ShopwellComponent {

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        redirectTo: 'frontend.cart.offcanvas',
    };

    init() {
        this.redirectInput = this.el.querySelector(this.options.redirectSelector);
        this.redirectParamInput = this.el.querySelector(this.options.redirectParamSelector);

        if (this.redirectInput) {
            this.redirectInput.value = this.options.redirectTo;
        }

        if (this.redirectParamInput) {
            this.redirectParamInput.disabled = true;
        }

        this.outOfStock = false;
        this.onFormSubmit = this.onFormSubmit.bind(this);
        this.onOutOfStock = this.onOutOfStock.bind(this);
        this.el.addEventListener('submit', this.onFormSubmit);
        this.el.addEventListener('QuantitySelector/OutOfStock', this.onOutOfStock);
    }

    onOutOfStock() {
        this.outOfStock = true;
        this.el.querySelectorAll('[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    }

    onFormSubmit(event) {
        event.preventDefault();

        if (this.outOfStock) {
            return;
        }

        let requestUrl = this.el.getAttribute('action');
        let formData = Shopwell.serializeForm(this.el);

        ({ requestUrl, formData } = Shopwell.emitInterception('BuyButton:PreSubmit', { requestUrl, formData }));

        Shopwell.emit('BuyButton:Submit', requestUrl, formData);

        window.PluginManager.callPluginMethod('OffCanvasCart', 'openOffCanvas', requestUrl, formData);
    }

    destroy() {
        this.el.removeEventListener('submit', this.onFormSubmit);
        this.el.removeEventListener('QuantitySelector/OutOfStock', this.onOutOfStock);
    }
}
