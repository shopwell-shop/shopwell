<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use WeChatPay\Formatter;

/**
 * Opens a wechat pay trade (api v3).
 *
 * The channel decides which product of wechat is used - and with it the appid, because an appid is
 * bound to the merchant id but differs between the products:
 *
 *  - `wechat_h5`     the h5 trade, wechat answers a url the app redirects the payer to,
 *  - `wechat_native` the native trade, wechat answers a code url the app renders as qr code,
 *  - `wechat_jsapi`  the jsapi trade of a public account, the answer is signed into the parameters
 *                    the page hands to `WeixinJSBridge`,
 *  - `wechat_mini`   the same trade inside a mini program,
 *  - `wechat_app`    the app trade, the answer is signed into the parameters the app sdk consumes.
 *
 * Everything after the trade was opened - querying, closing, refunding, verifying a notification -
 * runs on the merchant account alone, so this is the only action that dispatches on the channel.
 *
 * @internal
 */
#[Package('framework')]
final class WechatCaptureAction extends AbstractPaymentAction
{
    final public const URI_H5 = 'v3/pay/transactions/h5';

    final public const URI_NATIVE = 'v3/pay/transactions/native';

    final public const URI_JSAPI = 'v3/pay/transactions/jsapi';

    final public const URI_APP = 'v3/pay/transactions/app';

    final public const SETTING_H5_TYPE = 'h5_type';

    final public const DEFAULT_H5_TYPE = 'Wap';

    /**
     * The package the app sdk of wechat expects for an app trade.
     */
    final public const APP_PACKAGE = 'Sign=WXPay';

    /**
     * Wechat cuts the description at 127 characters.
     */
    private const MAX_DESCRIPTION_LENGTH = 127;

    protected string $apiClass = WechatApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $payload = $this->payload($model);

        switch ($api->channel()) {
            case WechatApi::CHANNEL_H5:
                $this->h5($model, $payload, self::URI_H5);

                return;
            case WechatApi::CHANNEL_NATIVE:
                $this->qrCode($model, $payload);

                return;
            case WechatApi::CHANNEL_JSAPI:
            case WechatApi::CHANNEL_MINI:
                $this->inApp($model, $payload, self::URI_JSAPI);

                return;
            case WechatApi::CHANNEL_APP:
                $this->inApp($model, $payload, self::URI_APP);

                return;
        }

        throw PaymentException::channelNotSupported($api->channel(), $api->provider());
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Capture::class);
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }

    /**
     * The h5 product: the payer is sent to wechat on its phone.
     *
     * @param array<string, mixed> $payload
     */
    private function h5(PaymentGatewayModel $model, array $payload, string $uri): void
    {
        $api = $this->api();

        $payload['scene_info'] = [
            'payer_client_ip' => $this->stringValue($model, PaymentGatewayModel::CLIENT_IP),
            'h5_info' => ['type' => $api->setting(self::SETTING_H5_TYPE, self::DEFAULT_H5_TYPE)],
        ];

        $answer = $this->post($model, $uri, $payload);

        $payUrl = $answer['h5_url'] ?? null;

        if (!\is_string($payUrl) || $payUrl === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a payment url');
        }

        $this->setResult($model, new PayResult(new PayParams(
            flow: PaymentFlow::REDIRECT,
            payUrl: $payUrl,
            httpMethod: 'GET',
            extra: ['prepay_id' => $answer['prepay_id'] ?? null],
        )));
    }

    /**
     * The native product: wechat answers a code url the app renders as a qr code.
     *
     * @param array<string, mixed> $payload
     */
    private function qrCode(PaymentGatewayModel $model, array $payload): void
    {
        $api = $this->api();

        $answer = $this->post($model, self::URI_NATIVE, $payload);

        $codeUrl = $answer['code_url'] ?? null;

        if (!\is_string($codeUrl) || $codeUrl === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a code url');
        }

        $this->setResult($model, new PayResult(new PayParams(
            flow: PaymentFlow::QRCODE,
            extra: ['qr_code' => $codeUrl, 'prepay_id' => $answer['prepay_id'] ?? null],
        )));
    }

    /**
     * The jsapi, mini program and app products: wechat answers a prepay id, which is signed into the
     * parameters the client of the payer consumes. The signature is the one thing these products share
     * with each other and the one thing they do not share with the two browser products.
     *
     * @param array<string, mixed> $payload
     */
    private function inApp(PaymentGatewayModel $model, array $payload, string $uri): void
    {
        $api = $this->api();

        $openid = $api->runtimeParam(WechatApi::RUNTIME_OPEN_ID);

        if ($openid !== null) {
            // the payer of this very payment, a runtime parameter and never part of the configuration
            $payload['payer'] = ['openid' => $openid];
        }

        $answer = $this->post($model, $uri, $payload);

        $prepayId = $answer['prepay_id'] ?? null;

        if (!\is_string($prepayId) || $prepayId === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a prepay id');
        }

        $extra = $uri === self::URI_APP
            ? $this->appParameters($api, $prepayId)
            : $this->jsapiParameters($api, $prepayId);

        $this->setResult($model, new PayResult(
            new PayParams(flow: PaymentFlow::SDK, extra: $extra),
            $prepayId
        ));
    }

    /**
     * The parameters `WeixinJSBridge` / `wx.chooseWXPay` of a page or mini program consume.
     *
     * @return array<string, string>
     */
    private function jsapiParameters(WechatApi $api, string $prepayId): array
    {
        $package = 'prepay_id=' . $prepayId;
        $timestamp = (string) Formatter::timestamp();
        $nonce = Formatter::nonce();

        // wechat verifies exactly this message, every piece on its own line
        $message = Formatter::joinedByLineFeed($api->appId(), $timestamp, $nonce, $package);

        return [
            'appId' => $api->appId(),
            'timeStamp' => $timestamp,
            'nonceStr' => $nonce,
            'package' => $package,
            'signType' => 'RSA',
            'paySign' => $api->sign($message),
        ];
    }

    /**
     * The parameters the app sdk of wechat consumes.
     *
     * @return array<string, string>
     */
    private function appParameters(WechatApi $api, string $prepayId): array
    {
        $timestamp = (string) Formatter::timestamp();
        $nonce = Formatter::nonce();

        $message = Formatter::joinedByLineFeed($api->appId(), $timestamp, $nonce, $prepayId);

        return [
            'appid' => $api->appId(),
            'partnerid' => $api->requireCredential(WechatApi::MCH_ID),
            'prepayid' => $prepayId,
            'package' => self::APP_PACKAGE,
            'noncestr' => $nonce,
            'timestamp' => $timestamp,
            'sign' => $api->sign($message),
        ];
    }

    /**
     * The trade every one of the five products starts with. The appid and the merchant id come from
     * different places on purpose - see {@see WechatApi::appId()} and the credentials.
     *
     * @return array<string, mixed>
     */
    private function payload(PaymentGatewayModel $model): array
    {
        $api = $this->api();

        $payload = [
            'appid' => $api->appId(),
            'mchid' => $api->requireCredential(WechatApi::MCH_ID),
            'description' => mb_substr($this->stringValue($model, PaymentGatewayModel::SUBJECT), 0, self::MAX_DESCRIPTION_LENGTH),
            // the platform sends its own payment number, wechat sends it back in the notification
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'notify_url' => $api->notifyUrl(),
            'amount' => [
                'total' => $this->intValue($model, PaymentGatewayModel::AMOUNT),
                'currency' => $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY'),
            ],
        ];

        $attach = $this->stringValue($model, PaymentGatewayModel::ATTACH);
        if ($attach !== '') {
            $payload['attach'] = $attach;
        }

        $expireAt = $this->value($model, PaymentGatewayModel::EXPIRE_AT);
        if ($expireAt instanceof \DateTimeInterface) {
            $payload['time_expire'] = $expireAt->format(\DateTimeInterface::ATOM);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(PaymentGatewayModel $model, string $uri, array $payload): array
    {
        $api = $this->api();

        $this->recordRequest($model, $payload);

        try {
            $answer = $api->post($uri, $payload);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage());
        }

        $this->recordResponse($model, $answer);

        return $answer;
    }
}
