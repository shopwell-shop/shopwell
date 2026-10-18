import QRCode from 'qrcode';

/**
 * What the browser has to do after a payment was opened.
 *
 * Two account pages collect money - the recharge of the wallet and the purchase of a membership - and
 * both are handed over to a channel in exactly the same way. This module is that one way, so a new
 * flow is supported on every page at once instead of being copied into each plugin.
 *
 * It deliberately knows nothing about what is being paid: the caller passes the answer of the payment
 * route and gets back what to render.
 *
 * @sw-package checkout
 */

export const FLOW_REDIRECT = 'redirect';
export const FLOW_QRCODE = 'qrcode';
export const FLOW_SDK = 'sdk';
export const FLOW_UNSUPPORTED = 'unsupported';

/**
 * @param {{params?: {flow?: string, pay_url?: string, html_form?: string, extra?: object}}} payment
 *
 * @returns {{kind: string, payUrl?: string, htmlForm?: string, qrContent?: string, sdkParams?: object}}
 */
export function resolveHandover(payment) {
    const params = payment?.params ?? {};

    if (params.flow === FLOW_REDIRECT) {
        // a POST flow of a channel is answered as a ready form, which must be sent by the browser
        // itself - there is no url to send it to
        if (params.html_form) {
            return { kind: FLOW_REDIRECT, htmlForm: params.html_form };
        }

        if (params.pay_url) {
            return { kind: FLOW_REDIRECT, payUrl: params.pay_url };
        }
    }

    if (params.flow === FLOW_QRCODE && params.extra?.qr_code) {
        return { kind: FLOW_QRCODE, qrContent: params.extra.qr_code };
    }

    if (params.flow === FLOW_SDK && params.extra) {
        return { kind: FLOW_SDK, sdkParams: params.extra };
    }

    return { kind: FLOW_UNSUPPORTED };
}

/**
 * Sends a form the channel built, e.g. the signed request of a pending Alipay trade.
 */
export function submitHtmlForm(html) {
    const container = document.createElement('div');
    container.innerHTML = html;

    const form = container.querySelector('form');

    if (!form) {
        return false;
    }

    document.body.appendChild(container);
    form.submit();

    return true;
}

/**
 * @param {HTMLElement} target
 * @param {string} content
 * @param {number} size
 */
export function renderQrCode(target, content, size = 220) {
    return new Promise((resolve, reject) => {
        QRCode.toCanvas(content, { width: size, margin: 1 }, (error, canvas) => {
            if (error) {
                reject(error);

                return;
            }

            target.innerHTML = '';
            target.appendChild(canvas);
            resolve();
        });
    });
}

/**
 * Whether the payment can be paid from inside the browser at all.
 *
 * The WeChat bridge is only injected into a page that runs inside WeChat; everywhere else the channel
 * cannot be used and the page says so instead of rendering a code nobody can scan.
 *
 * @returns {boolean}
 */
export function canPayWithSdk(params) {
    return Boolean(window.WeixinJSBridge) && Boolean(params?.paySign);
}

/**
 * @param {object} params the sdk parameters of the channel
 * @param {function(string): void} onResult receives `ok`, `cancel` or `fail`
 */
export function payWithSdk(params, onResult) {
    window.WeixinJSBridge.invoke('getBrandWCPayRequest', params, (response) => {
        const message = response?.err_msg ?? '';
        onResult(message === 'get_brand_wcpay_request:ok' ? 'ok' : message === 'get_brand_wcpay_request:cancel' ? 'cancel' : 'fail');
    });
}

/**
 * Polls a payment until it reaches a state the caller considers final.
 *
 * A scanned code is reported back by the channel on its own schedule, so the page cannot know when the
 * money arrived. Polling the row the shop wrote - and not the gateway - keeps that honest: the state
 * only changes once the settlement booked it.
 *
 * @sw-package checkout
 */
export default class PaymentPoller {
    /**
     * @param {object} options
     * @param {function(): Promise<object|null>} options.probe
     * @param {function(object): boolean} options.onState returns true when polling may stop
     * @param {number} options.interval
     * @param {number} options.timeout
     */
    constructor({ probe, onState, interval = 4000, timeout = 600000 }) {
        this._probe = probe;
        this._onState = onState;
        this._interval = interval;
        this._timeout = timeout;
        this._timer = null;
        this._deadline = 0;
        this._running = false;
    }

    start() {
        this.stop();
        this._running = true;
        this._deadline = Date.now() + this._timeout;

        void this._tick();
    }

    stop() {
        this._running = false;

        if (this._timer !== null) {
            window.clearTimeout(this._timer);
            this._timer = null;
        }
    }

    async _tick() {
        if (!this._running) {
            return;
        }

        const state = await this._probe();

        if (!this._running) {
            return;
        }

        if (state !== null && this._onState(state) === true) {
            this.stop();

            return;
        }

        if (Date.now() >= this._deadline) {
            this.stop();

            return;
        }

        this._timer = window.setTimeout(() => this._tick(), this._interval);
    }
}
