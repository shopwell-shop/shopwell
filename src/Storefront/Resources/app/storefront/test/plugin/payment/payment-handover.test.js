import {
    FLOW_QRCODE,
    FLOW_REDIRECT,
    FLOW_SDK,
    FLOW_UNSUPPORTED,
    canPayWithSdk,
    resolveHandover,
    submitHtmlForm,
} from 'src/plugin/payment/payment-handover';

describe('payment-handover', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        delete window.WeixinJSBridge;
    });

    test('hands a redirect flow over as a url', () => {
        expect(resolveHandover({ params: { flow: 'redirect', pay_url: 'https://pay.test/1', http_method: 'GET' } })).toStrictEqual({
            kind: FLOW_REDIRECT,
            payUrl: 'https://pay.test/1',
        });
    });

    test('prefers the ready form of a post flow over the url', () => {
        expect(resolveHandover({ params: { flow: 'redirect', pay_url: 'https://pay.test/1', html_form: '<form></form>' } })).toStrictEqual({
            kind: FLOW_REDIRECT,
            htmlForm: '<form></form>',
        });
    });

    test('hands a scanned flow over as the content of the code', () => {
        expect(resolveHandover({ params: { flow: 'qrcode', extra: { qr_code: 'weixin://wxpay/1' } } })).toStrictEqual({
            kind: FLOW_QRCODE,
            qrContent: 'weixin://wxpay/1',
        });
    });

    test('hands an sdk flow over as the payload', () => {
        expect(resolveHandover({ params: { flow: 'sdk', extra: { paySign: 'abc' } } })).toStrictEqual({
            kind: FLOW_SDK,
            sdkParams: { paySign: 'abc' },
        });
    });

    test('reports a flow it cannot handle instead of guessing', () => {
        expect(resolveHandover({ params: { flow: 'unknown' } }).kind).toBe(FLOW_UNSUPPORTED);
        expect(resolveHandover({}).kind).toBe(FLOW_UNSUPPORTED);
        expect(resolveHandover({ params: { flow: 'qrcode' } }).kind).toBe(FLOW_UNSUPPORTED);
    });

    test('refuses a form answer that does not contain a form', () => {
        expect(submitHtmlForm('<div>nope</div>')).toBe(false);
    });

    test('only allows the sdk inside the wechat bridge', () => {
        expect(canPayWithSdk({ paySign: 'abc' })).toBe(false);

        window.WeixinJSBridge = { invoke: jest.fn() };

        expect(canPayWithSdk({})).toBe(false);
        expect(canPayWithSdk({ paySign: 'abc' })).toBe(true);
    });
});
