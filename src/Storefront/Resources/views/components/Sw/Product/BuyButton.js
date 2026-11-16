export default class BuyButton extends ShopwellComponent {

    static options = {
        redirectSelector: '[name="redirectTo"]',
        redirectParamSelector: '[data-redirect-parameters="true"]',
        quantityFallbackSelector: '[data-quantity-fallback="true"]',
        redirectTo: 'frontend.cart.offcanvas',
    };

    init() {
        this.redirectInput = this.el.querySelector(this.options.redirectSelector);
        this.redirectParamInput = this.el.querySelector(this.options.redirectParamSelector);
        this.quantityFallback = this.el.querySelector(this.options.quantityFallbackSelector);

        if (this.redirectInput) {
            this.redirectInput.value = this.options.redirectTo;
        }

        if (this.redirectParamInput) {
            this.redirectParamInput.disabled = true;
        }

        if (this.quantityFallback) {
            // A quantity selector linked through the `form` attribute supplies the quantity, so the fallback must not override it.
            this.quantityFallback.disabled = Array.from(this.el.elements)
                .some((field) => field !== this.quantityFallback && field.name === this.quantityFallback.name);
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
