import MembershipPurchasePlugin from 'src/plugin/loyalty/membership-purchase.plugin';
import { renderQrCode } from 'src/plugin/payment/payment-handover';
import template from './membership-purchase.plugin.template.html';

// the qr code is drawn on a canvas, which jsdom does not implement - the plugin only needs to know
// that the content was accepted
jest.mock('src/plugin/payment/payment-handover', () => {
    const actual = jest.requireActual('src/plugin/payment/payment-handover');

    return {
        __esModule: true,
        ...actual,
        renderQrCode: jest.fn(),
    };
});

const options = {
    purchaseUrl: '/account/loyalty/membership',
    statusUrl: '/account/loyalty/json',
    returnUrl: 'https://shop.test/account/loyalty',
    qrHintTemplate: '请使用「%channel%」扫描二维码',
    loyaltyMembershipProcessing: '正在创建支付',
    loyaltyMembershipErrorGeneric: '开通失败',
    loyaltyMembershipSdkUnsupported: '请在微信内打开',
    loyaltyMembershipOpen: '支付未完成',
    loyaltyMembershipActivated: '会员已开通',
    loyaltyMembershipFailed: '开通未成功',
    pollInterval: 10,
    pollTimeout: 1000,
};

function jsonResponse(payload, ok = true) {
    return { ok, json: () => Promise.resolve(payload) };
}

function createPlugin() {
    const element = document.querySelector('[data-membership-purchase]');

    return new MembershipPurchasePlugin(element, options, 'MembershipPurchase');
}

describe('MembershipPurchasePlugin', () => {
    beforeEach(() => {
        document.body.innerHTML = template;

        renderQrCode.mockResolvedValue();

        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({ get: jest.fn().mockReturnValue([]) }),
        };

        global.fetch = jest.fn().mockResolvedValue(jsonResponse({ success: true }));
    });

    afterEach(() => {
        jest.restoreAllMocks();
        document.body.innerHTML = '';
    });

    test('posts the plan, the channel and the return url', async () => {
        const plugin = createPlugin();
        jest.spyOn(plugin, '_watch').mockImplementation(() => {});

        global.fetch.mockResolvedValueOnce(
            jsonResponse({
                success: true,
                order: { id: 'order-1', amount: 199, currency: 'CNY', params: { flow: 'qrcode', extra: { qr_code: 'weixin://wxpay/1' } } },
            }),
        );

        await plugin._purchase(document.querySelector('[data-membership-plan-id]'));

        const [url, request] = global.fetch.mock.calls[0];

        expect(url).toBe(options.purchaseUrl);
        expect(request.method).toBe('POST');
        expect(request.body.get('planId')).toBe('plan-1');
        expect(request.body.get('channel')).toBe('wechat_qr');
        expect(request.body.get('returnUrl')).toBe(options.returnUrl);
    });

    test('renders the code and watches the order', async () => {
        const plugin = createPlugin();
        const watchSpy = jest.spyOn(plugin, '_watch').mockImplementation(() => {});

        global.fetch.mockResolvedValueOnce(
            jsonResponse({
                success: true,
                order: { id: 'order-1', amount: 199, currency: 'CNY', params: { flow: 'qrcode', extra: { qr_code: 'weixin://wxpay/1' } } },
            }),
        );

        await plugin._purchase(document.querySelector('[data-membership-plan-id]'));

        expect(document.querySelector('[data-membership-purchase-qrcode]').classList.contains('d-none')).toBe(false);
        expect(document.querySelector('[data-membership-purchase-qrcode-hint]').textContent).toBe('请使用「微信扫码」扫描二维码');
        expect(watchSpy).toHaveBeenCalledWith('order-1');
    });

    test('shows the generic error and re-enables the buttons', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: false, errorCode: 'CHECKOUT__LOYALTY_PLAN_NOT_PURCHASABLE' }, false));

        await plugin._purchase(document.querySelector('[data-membership-plan-id]'));

        expect(document.querySelector('[data-membership-purchase-message]').textContent).toBe(options.loyaltyMembershipErrorGeneric);
        expect(document.querySelector('[data-membership-plan-id]').disabled).toBe(false);
    });

    test('reports an activated membership', () => {
        const plugin = createPlugin();
        const publishSpy = jest.spyOn(plugin.$emitter, 'publish').mockImplementation(() => {});

        expect(plugin._onState({ state: 'activated' })).toBe(true);
        expect(document.querySelector('[data-membership-purchase-message]').textContent).toBe(options.loyaltyMembershipActivated);
        expect(publishSpy).toHaveBeenCalledWith('membershipPurchaseSuccess');
    });

    test('stops on a failed order', () => {
        const plugin = createPlugin();
        const errorSpy = jest.spyOn(plugin, '_publishError').mockImplementation(() => {});

        expect(plugin._onState({ state: 'failed' })).toBe(true);
        expect(document.querySelector('[data-membership-purchase-message]').textContent).toBe(options.loyaltyMembershipFailed);
        expect(errorSpy).toHaveBeenCalledWith('membership-order-failed');
    });

    test('keeps watching an order that is still open', () => {
        const plugin = createPlugin();

        expect(plugin._onState({ state: 'open' })).toBe(false);
        expect(document.querySelector('[data-membership-purchase-message]').textContent).toBe(options.loyaltyMembershipOpen);
    });

    test('reads the state of the order from the membership page', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(
            jsonResponse({
                success: true,
                loyalty: { membershipOrders: [{ id: 'order-2', state: 'open' }, { id: 'order-1', state: 'activated' }] },
            }),
        );

        expect(await plugin._fetchOrder('order-1')).toStrictEqual({ id: 'order-1', state: 'activated' });
        expect(global.fetch).toHaveBeenCalledWith(options.statusUrl, { method: 'GET' });
    });

    test('returns null when the order is not part of the answer', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: true, loyalty: { membershipOrders: [] } }));

        expect(await plugin._fetchOrder('order-1')).toBeNull();
    });

    test('returns null when the membership page answers an error', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: false }, false));

        expect(await plugin._fetchOrder('order-1')).toBeNull();
    });
});
