<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
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
 * What the action decides is the business content of a trade and what an answer means for the payer;
 * how a product is called at alipay is the part of {@see AlipayApi}.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayCaptureAction extends AbstractPaymentAction
{
    final public const METHOD_WAP_PAY = 'alipay.trade.wap.pay';

    final public const METHOD_PAGE_PAY = 'alipay.trade.page.pay';

    final public const METHOD_APP_PAY = 'alipay.trade.app.pay';

    final public const METHOD_PRECREATE = 'alipay.trade.precreate';

    final public const METHOD_CREATE = 'alipay.trade.create';

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

    final public const RESPONSE_TRADE_NO = 'trade_no';

    /**
     * The longest payment window alipay accepts, as the minutes of `timeout_express`.
     */
    final public const MAX_TIMEOUT_MINUTES = 21600;

    protected string $apiClass = AlipayApi::class;

    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
    }

    public function capability(): PaymentCapability
    {
        return PaymentCapability::CAPTURE;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        switch ($api->channel()) {
            case AlipayProducts::H5:
                $this->redirectFlow($model, self::METHOD_WAP_PAY, self::PRODUCT_CODE_WAP);

                return;
            case AlipayProducts::WEB:
                $this->redirectFlow($model, self::METHOD_PAGE_PAY, self::PRODUCT_CODE_PAGE);

                return;
            case AlipayProducts::QR:
                $this->qrFlow($model, self::PRODUCT_CODE_QR);

                return;
            case AlipayProducts::APP:
                $this->appFlow($model, self::PRODUCT_CODE_APP);

                return;
            case AlipayProducts::MINI:
                $this->miniFlow($model);

                return;
        }

        throw PaymentProviderException::channelNotSupported($api->channel(), $api->provider());
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

        $httpMethod = self::DEFAULT_HTTP_METHOD;

        try {
            $answer = $api->paymentPage($method, $httpMethod, $parameters);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, [self::RESPONSE_HTTP_METHOD => $httpMethod, self::RESPONSE_ANSWER => $answer]);

        $params = new PaymentHandover(flow: PaymentInteraction::REDIRECT, payUrl: $answer, httpMethod: $httpMethod, extra: ['method' => $method]);

        $this->setResult($model, new PaymentStartResult($params));
    }

    /**
     * The scan flow: alipay answers a qr code the app renders for the payer.
     */
    private function qrFlow(PaymentGatewayModel $model, string $productCode): void
    {
        $api = $this->api();

        $content = $this->bizContent($model, $productCode);
        $this->recordRequest($model, ['method' => self::METHOD_PRECREATE, 'content' => $content]);

        try {
            // the qr code carries the trade, alipay notifies the platform the very same way as in the
            // redirect flow - the api of the interface does not change with the channel
            $answer = $api->precreatePayment($content);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $qrCode = $answer[self::RESPONSE_QR_CODE] ?? null;

        if (!\is_string($qrCode) || $qrCode === '') {
            // alipay answers its default response when it rejects the call
            $this->recordResponse($model, ['error' => 'the interface rejected the precreate call']);

            throw PaymentGatewayException::gatewayError($api->channel(), 'the interface did not answer a qr code');
        }

        $this->recordResponse($model, [self::RESPONSE_QR_CODE => $qrCode]);

        $this->setResult($model, new PaymentStartResult(new PaymentHandover(
            flow: PaymentInteraction::QRCODE,
            extra: ['qr_code' => $qrCode, 'method' => self::METHOD_PRECREATE],
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
            // the notification url is a public parameter as well, so it travels next to the business
            // content and not inside of it
            $orderString = $api->paymentOrderString(self::METHOD_APP_PAY, [
                'biz_content' => $content,
                'notify_url' => $api->notifyUrl(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        if ($orderString === '') {
            $this->recordResponse($model, ['error' => 'the interface did not answer an order string']);

            throw PaymentGatewayException::gatewayError($api->channel(), 'the interface did not answer an order string');
        }

        $this->recordResponse($model, ['order_str' => $orderString]);

        $this->setResult($model, new PaymentStartResult(new PaymentHandover(
            flow: PaymentInteraction::SDK,
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

        $this->recordRequest($model, ['method' => self::METHOD_CREATE, 'content' => $content]);

        try {
            $answer = $api->createPayment($content);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentGatewayException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $tradeNo = $answer[self::RESPONSE_TRADE_NO] ?? null;

        if (!\is_string($tradeNo) || $tradeNo === '') {
            $this->recordResponse($model, ['error' => 'the interface rejected the create call']);

            throw PaymentGatewayException::gatewayError($api->channel(), 'the interface did not answer a trade number');
        }

        $this->recordResponse($model, [self::RESPONSE_TRADE_NO => $tradeNo]);

        $this->setResult($model, new PaymentStartResult(
            new PaymentHandover(flow: PaymentInteraction::SDK, extra: ['trade_no' => $tradeNo, 'method' => self::METHOD_CREATE]),
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
