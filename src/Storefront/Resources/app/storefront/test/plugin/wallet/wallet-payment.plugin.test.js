import WalletPaymentPlugin from 'src/plugin/wallet/wallet-payment.plugin';
import template from './wallet-payment.plugin.template.html';

describe('WalletPaymentPlugin tests', () => {
    let reloadSpy;

    function jsonResponse(payload, ok = true) {
        return { ok, json: () => Promise.resolve(payload) };
    }

    function createPlugin(options = {}) {
        const element = document.querySelector('[data-wallet-payment]');

        return new WalletPaymentPlugin(element, options, 'WalletPayment');
    }

    beforeEach(() => {
        document.body.innerHTML = template;

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({ get: jest.fn().mockReturnValue([]) }),
        };

        global.fetch = jest.fn().mockResolvedValue(jsonResponse({ success: true, wallet: { wallets: [] } }));

        reloadSpy = jest.spyOn(WalletPaymentPlugin.prototype, '_reloadCart').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
        document.body.innerHTML = '';
    });

    test('renders the balance of the active currency', async () => {
        global.fetch.mockResolvedValue(jsonResponse({
            success: true,
            wallet: {
                wallets: [
                    { currencyId: 'currency-1', balance: 42.5 },
                    { currencyId: 'currency-2', balance: 10 },
                ],
            },
        }));

        const plugin = createPlugin({ infoUrl: '/account/wallet/json', paymentUrl: '/account/wallet/payment', currencyId: 'currency-1' });

        await plugin._loadWallet();

        expect(document.querySelector('[data-wallet-payment-balance]').textContent).toBe('42.5');
    });

    test('hides the plugin when the info endpoint is not available', async () => {
        global.fetch.mockResolvedValueOnce(jsonResponse({}, false));

        const plugin = createPlugin({ infoUrl: '/account/wallet/json', paymentUrl: '/account/wallet/payment', currencyId: 'currency-1' });

        await plugin._loadWallet();

        expect(document.querySelector('[data-wallet-payment]').classList.contains('d-none')).toBe(true);
    });

    test('posts the entered amount and reloads the cart on success', async () => {
        const plugin = createPlugin({ infoUrl: '/account/wallet/json', paymentUrl: '/account/wallet/payment', currencyId: 'currency-1' });

        document.querySelector('[data-wallet-payment-input]').value = '12.5';

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: true }));

        await plugin._onSubmit();

        expect(global.fetch).toHaveBeenLastCalledWith('/account/wallet/payment', expect.objectContaining({ method: 'POST' }));
        expect(reloadSpy).toHaveBeenCalled();
    });

    test('reports the error and stays enabled on failure', async () => {
        const plugin = createPlugin({ infoUrl: '/account/wallet/json', paymentUrl: '/account/wallet/payment', currencyId: 'currency-1' });
        const errorSpy = jest.spyOn(plugin, '_showError').mockImplementation(() => {});

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: false, errorCode: 'CHECKOUT__WALLET_INSUFFICIENT_BALANCE' }, false));

        await plugin._onSubmit();

        expect(reloadSpy).not.toHaveBeenCalled();
        expect(errorSpy).toHaveBeenCalledWith('CHECKOUT__WALLET_INSUFFICIENT_BALANCE');
        expect(document.querySelector('[data-wallet-payment-submit]').disabled).toBe(false);
    });
});
