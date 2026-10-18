import WalletTopUpPlugin from 'src/plugin/wallet/wallet-top-up.plugin';
import { renderQrCode } from 'src/plugin/payment/payment-handover';
import template from './wallet-top-up.plugin.template.html';

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
    topUpUrl: '/account/wallet/top-up',
    statusUrl: '/account/wallet/top-up/__TOP_UP_ID__',
    returnUrl: 'https://shop.test/account/wallet',
    currency: 'CNY',
    min: 10,
    max: 10000,
    presets: [100, 500],
    qrHintTemplate: '请使用「%channel%」扫描二维码',
    walletTopUpProcessing: '正在创建支付',
    walletTopUpErrorGeneric: '充值失败',
    walletTopUpErrorAmount: '金额不正确',
    walletTopUpSdkUnsupported: '请在微信内打开',
    walletTopUpOpen: '支付未完成',
    walletTopUpPaid: '充值成功',
    walletTopUpFailed: '充值失败',
    walletTopUpStatusPending: '确认中',
    pollInterval: 10,
    pollTimeout: 1000,
};

function jsonResponse(payload, ok = true) {
    return { ok, json: () => Promise.resolve(payload) };
}

function createPlugin() {
    const element = document.querySelector('[data-wallet-top-up]');

    return new WalletTopUpPlugin(element, options, 'WalletTopUp');
}

function submitEvent() {
    return { preventDefault: jest.fn() };
}

describe('WalletTopUpPlugin', () => {
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

    test('fills the amount when a preset is chosen', () => {
        const plugin = createPlugin();

        plugin.el.querySelector('[data-wallet-top-up-preset]').click();

        expect(document.querySelector('[data-wallet-top-up-amount]').value).toBe('500');
    });

    test('rejects an amount below the configured minimum without asking the server', async () => {
        const plugin = createPlugin();

        document.querySelector('[data-wallet-top-up-amount]').value = '5';

        await plugin._onSubmit(submitEvent());

        expect(global.fetch).not.toHaveBeenCalled();
        expect(document.querySelector('[data-wallet-top-up-message]').textContent).toBe(options.walletTopUpErrorAmount);
        expect(document.querySelector('[data-wallet-top-up-feedback]').classList.contains('alert-danger')).toBe(true);
    });

    test('posts amount, channel and return url to the top up endpoint', async () => {
        const plugin = createPlugin();
        jest.spyOn(plugin, '_watch').mockImplementation(() => {});

        document.querySelector('[data-wallet-top-up-amount]').value = '500';

        global.fetch.mockResolvedValueOnce(
            jsonResponse({
                success: true,
                topUp: { id: 'top-up-1', amount: 500, currency: 'CNY', channel: 'alipay_redirect', params: { flow: 'redirect', pay_url: 'https://pay.test/1' } },
            }),
        );

        await plugin._onSubmit(submitEvent());

        const [url, request] = global.fetch.mock.calls[0];
        const body = request.body;

        expect(url).toBe(options.topUpUrl);
        expect(request.method).toBe('POST');
        expect(body.get('amount')).toBe('500');
        expect(body.get('channel')).toBe('wechat_qr');
        expect(body.get('returnUrl')).toBe(options.returnUrl);
    });

    test('renders the code and starts watching a scanned payment', async () => {
        const plugin = createPlugin();
        const watchSpy = jest.spyOn(plugin, '_watch').mockImplementation(() => {});

        document.querySelector('[data-wallet-top-up-amount]').value = '500';

        global.fetch.mockResolvedValueOnce(
            jsonResponse({
                success: true,
                topUp: {
                    id: 'top-up-1',
                    amount: 500,
                    currency: 'CNY',
                    channel: 'wechat_qr',
                    params: { flow: 'qrcode', extra: { qr_code: 'weixin://wxpay/bizpayurl?pr=abc' } },
                },
            }),
        );

        await plugin._onSubmit(submitEvent());

        expect(document.querySelector('[data-wallet-top-up-qrcode]').classList.contains('d-none')).toBe(false);
        expect(document.querySelector('[data-wallet-top-up-qrcode-hint]').textContent).toBe('请使用「微信扫码」扫描二维码');
        expect(watchSpy).toHaveBeenCalledWith('top-up-1');
    });

    test('shows the generic error and stays usable when the endpoint refuses', async () => {
        const plugin = createPlugin();

        document.querySelector('[data-wallet-top-up-amount]').value = '500';

        global.fetch.mockResolvedValueOnce(
            jsonResponse({ success: false, errorCode: 'CHECKOUT__WALLET_TOP_UP_ABOVE_MAXIMUM' }, false),
        );

        await plugin._onSubmit(submitEvent());

        expect(document.querySelector('[data-wallet-top-up-message]').textContent).toBe(options.walletTopUpErrorGeneric);
        expect(document.querySelector('[data-wallet-top-up-submit]').disabled).toBe(false);
    });

    test('reports a credited top up and stops watching', () => {
        const plugin = createPlugin();
        const publishSpy = jest.spyOn(plugin.$emitter, 'publish').mockImplementation(() => {});

        expect(plugin._onState({ state: 'credited' })).toBe(true);
        expect(document.querySelector('[data-wallet-top-up-message]').textContent).toBe(options.walletTopUpPaid);
        expect(publishSpy).toHaveBeenCalledWith('walletTopUpSuccess');
    });

    test('stops watching and reports a failed top up', () => {
        const plugin = createPlugin();
        const errorSpy = jest.spyOn(plugin, '_publishError').mockImplementation(() => {});

        const done = plugin._onState({ state: 'failed' });

        expect(done).toBe(true);
        expect(document.querySelector('[data-wallet-top-up-message]').textContent).toBe(options.walletTopUpFailed);
        expect(errorSpy).toHaveBeenCalledWith('top-up-failed');
    });

    test('keeps watching a top up that is still open', () => {
        const plugin = createPlugin();

        expect(plugin._onState({ state: 'open' })).toBe(false);
        expect(document.querySelector('[data-wallet-top-up-message]').textContent).toBe(options.walletTopUpOpen);
    });

    test('asks the status endpoint with the encoded top up id', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: true, topUp: { id: 'top-up-1', state: 'open' } }));

        await plugin._fetchStatus('top-up-1');

        expect(global.fetch).toHaveBeenCalledWith('/account/wallet/top-up/top-up-1', { method: 'GET' });
    });

    test('returns null when the status endpoint answers an error', async () => {
        const plugin = createPlugin();

        global.fetch.mockResolvedValueOnce(jsonResponse({ success: false }, false));

        expect(await plugin._fetchStatus('top-up-1')).toBeNull();
    });
});
