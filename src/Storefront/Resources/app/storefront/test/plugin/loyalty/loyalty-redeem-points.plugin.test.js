import LoyaltyRedeemPointsPlugin from 'src/plugin/loyalty/loyalty-redeem-points.plugin';
import template from './loyalty-redeem-points.plugin.template.html';

describe('LoyaltyRedeemPointsPlugin tests', () => {
    let reloadSpy;

    function jsonResponse(payload, ok = true) {
        return { ok, json: () => Promise.resolve(payload) };
    }

    function createPlugin(options = {}) {
        const element = document.querySelector('[data-loyalty-redeem-points]');

        return new LoyaltyRedeemPointsPlugin(element, options, 'LoyaltyRedeemPoints');
    }

    beforeEach(() => {
        document.body.innerHTML = template;

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({ get: jest.fn().mockReturnValue([]) }),
        };

        global.fetch = jest.fn().mockResolvedValue(jsonResponse({ success: true, loyalty: { pointsBalance: 0 } }));

        reloadSpy = jest.spyOn(LoyaltyRedeemPointsPlugin.prototype, '_reloadCart').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
        document.body.innerHTML = '';
    });

    test('renders the points balance from the info endpoint', async () => {
        global.fetch.mockResolvedValue(jsonResponse({ success: true, loyalty: { pointsBalance: 120 } }));

        const plugin = createPlugin({ infoUrl: '/account/loyalty/json', redeemUrl: '/account/loyalty/redeem-points' });

        await plugin._loadProgram();

        expect(document.querySelector('[data-loyalty-redeem-points-balance]').textContent).toBe('120');
    });

    test('hides the plugin when the info endpoint is not available', async () => {
        global.fetch.mockResolvedValueOnce(jsonResponse({}, false));

        const plugin = createPlugin({ infoUrl: '/account/loyalty/json', redeemUrl: '/account/loyalty/redeem-points' });

        await plugin._loadProgram();

        expect(document.querySelector('[data-loyalty-redeem-points]').classList.contains('d-none')).toBe(true);
    });

    test('posts the entered points and reloads the cart on success', async () => {
        const plugin = createPlugin({ infoUrl: '/account/loyalty/json', redeemUrl: '/account/loyalty/redeem-points' });

        document.querySelector('[data-loyalty-redeem-points-input]').value = '50';

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: true }));

        await plugin._onSubmit();

        expect(global.fetch).toHaveBeenLastCalledWith('/account/loyalty/redeem-points', expect.objectContaining({ method: 'POST' }));
        expect(reloadSpy).toHaveBeenCalled();
    });

    test('reports the error and stays enabled on failure', async () => {
        const plugin = createPlugin({ infoUrl: '/account/loyalty/json', redeemUrl: '/account/loyalty/redeem-points' });
        const errorSpy = jest.spyOn(plugin, '_showError').mockImplementation(() => {});

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: false, errorCode: 'CHECKOUT__LOYALTY_INSUFFICIENT_POINTS' }, false));

        await plugin._onSubmit();

        expect(reloadSpy).not.toHaveBeenCalled();
        expect(errorSpy).toHaveBeenCalledWith('CHECKOUT__LOYALTY_INSUFFICIENT_POINTS');
        expect(document.querySelector('[data-loyalty-redeem-points-submit]').disabled).toBe(false);
    });
});
