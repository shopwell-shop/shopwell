import 'src/component-system/component';
import BuyButton from '../../../../views/components/Sw/Product/BuyButton';
import QuantitySelector from '../../../../views/components/Sw/Product/QuantitySelector';

/**
 * @sw-package framework
 */
describe('BuyButton quantity stock refresh', () => {
    let form;
    let buy;
    let quantity;

    beforeEach(() => {
        document.body.innerHTML = `
            <form action="/checkout/line-item/add">
                <div class="quantity-selector">
                    <button class="sw-product-quantity-selector__button--decrease" type="button">-</button>
                    <input name="lineItems[product][quantity]" class="sw-product-quantity-selector__input" value="2" min="1" max="10" step="1">
                    <button class="sw-product-quantity-selector__button--increase" type="button">+</button>
                </div>
                <button type="submit">Buy</button>
            </form>`;
        form = document.querySelector('form');
        window.PluginManager = { callPluginMethod: jest.fn() };
        window.Shopwell = {
            serializeForm: jest.fn(() => new FormData(form)),
            emitInterception: jest.fn((event, data) => data),
            emit: jest.fn(),
        };
        buy = new BuyButton(form);
        quantity = new QuantitySelector(form.querySelector('.quantity-selector'));
    });

    afterEach(() => {
        buy.destroy();
        quantity.destroy();
        document.body.innerHTML = '';
    });

    test('disables purchase when the real quantity component receives zero stock', () => {
        quantity.applyPurchaseLimit({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 0 });

        expect(form.querySelector('input').disabled).toBe(true);
        expect(form.querySelector('[type="submit"]').disabled).toBe(true);
    });

    test('prevents programmatic submit after the out-of-stock signal', () => {
        form.dispatchEvent(new CustomEvent('QuantitySelector/OutOfStock'));
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(window.PluginManager.callPluginMethod).not.toHaveBeenCalled();
        expect(window.Shopwell.serializeForm).not.toHaveBeenCalled();
    });

    test('submits normally while stock is available', () => {
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(window.PluginManager.callPluginMethod).toHaveBeenCalledWith(
            'OffCanvasCart', 'openOffCanvas', '/checkout/line-item/add', expect.any(FormData),
        );
    });

    test('removes the submit listener on destruction', () => {
        buy.destroy();
        const event = new Event('submit', { cancelable: true });
        form.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(window.PluginManager.callPluginMethod).not.toHaveBeenCalled();
    });

    test('removes the stock listener on destruction', () => {
        buy.destroy();
        form.dispatchEvent(new CustomEvent('QuantitySelector/OutOfStock'));

        expect(form.querySelector('[type="submit"]').disabled).toBe(false);
    });
});
