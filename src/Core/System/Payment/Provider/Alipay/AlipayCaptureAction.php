<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Model\AlipayTradeCreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradeCreateResponseModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateResponseModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Symfony\Component\Clock\NativeClock;

/**
 * Opens an alipay payment.
 *
 * This is the one place where the channel of a payment matters: every other call of alipay - querying,
 * closing, refunding, verifying a notification - works on the merchant account alone and does not care
 * which product the payment was opened with.
 *
 *  - `alipay_h5`  the mobile web gateway `alipay.trade.wap.pay`, the payer is redirected,
 *  - `alipay_web` the desktop web gateway `alipay.trade.page.pay`, the payer is redirected,
 *  - `alipay_qr`  `alipay.trade.precreate`, alipay answers a qr code the payer scans,
 *  - `alipay_app` `alipay.trade.app.pay`, alipay answers the order string the sdk of the app signs,
 *  - `alipay_mini` `alipay.trade.create`, the mini program then calls `my.tradePay` with the trade no.
 *
 * With the channel parameter `http_method=POST` the two gateway products answer with a self submitting
 * form instead of a url, which is what a desktop browser needs when the url would grow too long.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayCaptureAction extends AbstractPaymentAction
{
    final public const METHOD_WAP_PAY = 'alipay.trade.wap.pay';

    final public const METHOD_PAGE_PAY = 'alipay.trade.page.pay';

    final public const METHOD_APP_PAY = 'alipay.trade.app.pay';

    final public const PRODUCT_CODE_WAP = 'QUICK_WAP_WAY';

    final public const PRODUCT_CODE_PAGE = 'FAST_INSTANT_TRADE_PAY';

    final public const PRODUCT_CODE_QR = 'FACE_TO_FACE_PAYMENT';

    final public const PRODUCT_CODE_APP = 'QUICK_MSECURITY_PAY';

    final public const PRODUCT_CODE_MINI = 'JSAPI_PAY';

    /**
     * The runtime parameter of the mini program payment: the buyer id the trade is created for.
     */
    final public const RUNTIME_BUYER_ID = 'buyer_id';

    /**
     * The channel parameter of the mini program payment: the appid of the mini program that opens the
     * cashier. It falls back to the appid of the credentials, which is the mini program itself unless a
     * service provider creates the trade on behalf of a merchant.
     */
    final public const PARAM_OP_APP_ID = 'op_app_id';

    /**
     * The key of the gateway http method: declared as a channel parameter and accepted as a free
     * setting.
     */
    final public const SETTING_HTTP_METHOD = 'http_method';

    final public const DEFAULT_HTTP_METHOD = 'GET';

    /**
     * The keys the answers of this interface are recorded under. They are part of the contract with
     * {@see AlipayPayParamsDecoder}, which turns a recorded answer back into the parameters the payer
     * is handed to - the two must spell the keys in one place or a reopen silently degrades to a
     * second capture at alipay.
     */
    final public const RESPONSE_HTTP_METHOD = 'http_method';

    final public const RESPONSE_ANSWER = 'answer';

    final public const RESPONSE_QR_CODE = 'qr_code';

    /**
     * The longest payment window alipay accepts, as the minutes of `timeout_express`.
     */
    final public const MAX_TIMEOUT_MINUTES = 21600;

    protected string $apiClass = AlipayApi::class;

    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        switch ($api->channel()) {
            case AlipayApi::CHANNEL_H5:
                $this->redirectFlow($model, self::METHOD_WAP_PAY, self::PRODUCT_CODE_WAP);

                return;
            case AlipayApi::CHANNEL_WEB:
                $this->redirectFlow($model, self::METHOD_PAGE_PAY, self::PRODUCT_CODE_PAGE);

                return;
            case AlipayApi::CHANNEL_QR:
                $this->qrFlow($model, self::PRODUCT_CODE_QR);

                return;
            case AlipayApi::CHANNEL_APP:
                $this->appFlow($model, self::PRODUCT_CODE_APP);

                return;
            case AlipayApi::CHANNEL_MINI:
                $this->miniFlow($model);

                return;
        }

        throw PaymentException::channelNotSupported($api->channel(), $api->provider());
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Capture::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }

    /**
     * The two gateway products answer a url (or a form) the app sends the payer to.
     */
    private function redirectFlow(PaymentGatewayModel $model, string $method, string $productCode): void
    {
        $api = $this->api();

        $content = $this->bizContent($model, $productCode);
        $returnUrl = $api->returnUrl();

        if ($returnUrl !== '') {
            // where alipay sends the payer when they abandon the payment on its own pages
            $content['quit_url'] = $returnUrl;
        }

        $parameters = [
            'biz_content' => $content,
            'notify_url' => $api->notifyUrl(),
            'return_url' => $returnUrl,
        ];

        $this->recordRequest($model, ['method' => $method, 'content' => $content]);

        $httpMethod = $this->httpMethod();

        try {
            $answer = $api->genericApi()->pageExecute($method, $httpMethod, $parameters);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, [self::RESPONSE_HTTP_METHOD => $httpMethod, self::RESPONSE_ANSWER => $answer]);

        $params = $httpMethod === 'GET'
            ? new PayParams(flow: PaymentFlow::REDIRECT, payUrl: $answer, httpMethod: 'GET', extra: ['method' => $method])
            : new PayParams(flow: PaymentFlow::REDIRECT, httpMethod: 'POST', htmlForm: $answer, extra: ['method' => $method]);

        $this->setResult($model, new PayResult($params));
    }

    /**
     * The scan flow: alipay answers a qr code the app renders for the payer.
     */
    private function qrFlow(PaymentGatewayModel $model, string $productCode): void
    {
        $api = $this->api();

        $content = $this->bizContent($model, $productCode);
        $this->recordRequest($model, ['method' => 'alipay.trade.precreate', 'content' => $content]);

        $trade = new AlipayTradePrecreateModel();
        foreach ($content as $key => $value) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

            if (method_exists($trade, $setter)) {
                // the business content is written in the snake_case of the wire format, so the name of
                // the setter has to be derived from it - unknown keys are skipped above
                // @phpstan-ignore method.dynamicName
                $trade->{$setter}($value);
            }
        }

        $trade->setNotifyUrl($api->notifyUrl());

        try {
            // the qr code carries the trade, alipay notifies the platform the very same way as in the
            // redirect flow - the api of the interface does not change with the channel
            $answer = $api->tradeApi()->precreate($trade);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        if (!$answer instanceof AlipayTradePrecreateResponseModel) {
            // alipay answers its default response when it rejects the call
            $this->recordResponse($model, ['error' => 'the interface rejected the precreate call']);

            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a qr code');
        }

        $qrCode = $answer->getQrCode();

        $this->recordResponse($model, [self::RESPONSE_QR_CODE => $qrCode]);

        if (!\is_string($qrCode) || $qrCode === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a qr code');
        }

        $this->setResult($model, new PayResult(new PayParams(
            flow: PaymentFlow::QRCODE,
            extra: ['qr_code' => $qrCode, 'method' => 'alipay.trade.precreate'],
        )));
    }

    /**
     * The app flow: alipay answers the signed order string the sdk of the app hands to alipay.
     */
    private function appFlow(PaymentGatewayModel $model, string $productCode): void
    {
        $api = $this->api();

        $content = $this->bizContent($model, $productCode);
        $this->recordRequest($model, ['method' => self::METHOD_APP_PAY, 'content' => $content]);

        try {
            // the sdk turns the `biz_content` array into the json string of the wire format; handing it
            // the business content alone would send it as public parameters, which alipay rejects - the
            // notification url is a public parameter as well, so an app payment carries it here
            $orderString = $api->genericApi()->sdkExecute(self::METHOD_APP_PAY, [
                'biz_content' => $content,
                'notify_url' => $api->notifyUrl(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, ['order_str' => $orderString]);

        if (!\is_string($orderString) || $orderString === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer an order string');
        }

        $this->setResult($model, new PayResult(new PayParams(
            flow: PaymentFlow::SDK,
            extra: ['order_str' => $orderString, 'method' => self::METHOD_APP_PAY],
        )));
    }

    /**
     * The mini program flow: a trade is created for the buyer, the mini program pays the trade no.
     */
    private function miniFlow(PaymentGatewayModel $model): void
    {
        $api = $this->api();

        $content = $this->bizContent($model, self::PRODUCT_CODE_MINI);
        // the buyer is a runtime parameter: it belongs to the payer of this very payment and is never
        // part of the shop configuration
        $content[self::RUNTIME_BUYER_ID] = $api->runtimeParam(self::RUNTIME_BUYER_ID);

        // the jsapi product requires the appid of the mini program that opens the cashier; the appid of
        // the credentials is that mini program unless a service provider creates the trade for a merchant
        $opAppId = $api->param(self::PARAM_OP_APP_ID) ?? $api->credential(AlipayApi::APP_ID);
        if ($opAppId !== null) {
            $content[self::PARAM_OP_APP_ID] = $opAppId;
        }

        $this->recordRequest($model, ['method' => 'alipay.trade.create', 'content' => $content]);

        $trade = new AlipayTradeCreateModel();
        foreach ($content as $key => $value) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

            if (method_exists($trade, $setter)) {
                // the business content is written in the snake_case of the wire format, so the name of
                // the setter has to be derived from it - unknown keys are skipped above
                // @phpstan-ignore method.dynamicName
                $trade->{$setter}($value);
            }
        }

        $trade->setNotifyUrl($api->notifyUrl());

        try {
            $answer = $api->tradeApi()->create($trade);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        if (!$answer instanceof AlipayTradeCreateResponseModel) {
            // alipay answers its default response when it rejects the call
            $this->recordResponse($model, ['error' => 'the interface rejected the create call']);

            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a trade number');
        }

        $tradeNo = $answer->getTradeNo();

        $this->recordResponse($model, ['trade_no' => $tradeNo]);

        if (!\is_string($tradeNo) || $tradeNo === '') {
            throw PaymentException::gatewayError($api->channel(), 'the interface did not answer a trade number');
        }

        $this->setResult($model, new PayResult(
            new PayParams(flow: PaymentFlow::SDK, extra: ['trade_no' => $tradeNo, 'method' => 'alipay.trade.create']),
            $tradeNo
        ));
    }

    /**
     * The business content of a trade, which is what all five channels have in common.
     *
     * @return array<string, mixed>
     */
    private function bizContent(PaymentGatewayModel $model, string $productCode): array
    {
        $api = $this->api();
        $currency = $this->stringValue($model, PaymentGatewayModel::CURRENCY, 'CNY');

        $content = [
            // the platform sends its own payment number, it is the merchant order number alipay
            // sends back in the notification
            'out_trade_no' => $this->stringValue($model, PaymentGatewayModel::PAYMENT_NO),
            'total_amount' => $api->amount($this->intValue($model, PaymentGatewayModel::AMOUNT), $currency),
            'subject' => $this->stringValue($model, PaymentGatewayModel::SUBJECT),
            'product_code' => $productCode,
        ];

        $body = $this->stringValue($model, PaymentGatewayModel::BODY);
        if ($body !== '') {
            $content['body'] = $body;
        }

        $attach = $this->stringValue($model, PaymentGatewayModel::ATTACH);
        if ($attach !== '') {
            // handed back unchanged in the notification, so the app recognises its own payment
            $content['passback_params'] = $attach;
        }

        $expireAt = $this->value($model, PaymentGatewayModel::EXPIRE_AT);
        if ($expireAt instanceof \DateTimeInterface) {
            $content['timeout_express'] = $this->timeoutExpress($expireAt);
        }

        return $content;
    }

    /**
     * The declared channel parameter wins over the free settings, so the switch is reachable from the
     * administration: the form it renders is built from the channel parameters, the settings are a
     * free payload the api may send next to them.
     */
    private function httpMethod(): string
    {
        $api = $this->api();

        $method = $api->param(self::SETTING_HTTP_METHOD) ?? $api->setting(self::SETTING_HTTP_METHOD, self::DEFAULT_HTTP_METHOD);

        return strtoupper($method ?? self::DEFAULT_HTTP_METHOD);
    }

    /**
     * Alipay limits `timeout_express` to minutes, hours and days, and accepts 1m to 15d only.
     *
     * An expiry beyond that is cut at the upper bound instead of being sent as it is: alipay would
     * reject the whole trade, and a payment that stays open for 15 days is still better than a payment
     * that cannot be opened at all. Whether an order may live longer is a decision of the order - it
     * would be taken when the order is created, never here, where the trade is already being opened.
     */
    private function timeoutExpress(\DateTimeInterface $expireAt): string
    {
        $seconds = max(0, $expireAt->getTimestamp() - $this->clock->now()->getTimestamp());
        $minutes = min(self::MAX_TIMEOUT_MINUTES, max(1, (int) ceil($seconds / 60)));

        return $minutes . 'm';
    }
}
