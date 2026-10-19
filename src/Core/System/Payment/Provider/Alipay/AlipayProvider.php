<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * The alipay interface of the platform.
 *
 * One open platform application is one set of credentials, and every channel below is charged on that
 * very account - which is why the credentials live here and the products do not duplicate a single key.
 *
 * The declaration used to be `Provider/Resources/config/alipay.xml`; it is code now, so the interface
 * and the actions it consists of are in one place and a plugin can decorate the one without rewriting
 * the other.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayProvider extends AbstractPaymentProvider
{
    /**
     * The snippet key of the name the administration shows.
     */
    final public const LABEL = 'sw-settings-payment-provider.provider.alipay';

    /**
     * The alipay SDK only implements RSA2 (SHA256withRSA), so the select has a single option - it is
     * declared anyway, because the stored credential is read by the api and an installation that
     * already carries a value must not lose it.
     */
    final public const SIGN_TYPE_RSA2 = 'RSA2';

    final public const DEFAULT_GATEWAY_URL = 'https://openapi.alipay.com';

    public function __construct(
        private readonly AlipayCaptureAction $captureAction,
        private readonly AlipayRefundAction $refundAction,
        private readonly AlipayNotifyAction $notifyAction,
        private readonly AlipayQueryAction $queryAction,
        private readonly AlipayCloseAction $closeAction,
        private readonly AlipayTransferAction $transferAction,
        private readonly AlipayTransferQueryAction $transferQueryAction,
        private readonly AlipayNotificationExtractor $notificationExtractor,
        private readonly AlipayPayParamsDecoder $payParamsDecoder,
        private readonly AlipayTransferNotificationVerifier $transferNotificationVerifier,
    ) {
    }

    public function getName(): string
    {
        return 'alipay';
    }

    public function getLabel(): string
    {
        return self::LABEL;
    }

    public function getApiClass(): string
    {
        return AlipayApi::class;
    }

    /**
     * The order is the order the gateway gets them in, which is also the order payum asks them in for a
     * request: every action answers `supports()` for exactly one request, so the order only matters for
     * the two actions that could both claim a capture.
     *
     * @return list<AbstractPaymentAction>
     */
    public function getActions(): array
    {
        return [
            $this->captureAction,
            $this->refundAction,
            $this->notifyAction,
            $this->queryAction,
            $this->closeAction,
            $this->transferAction,
            $this->transferQueryAction,
        ];
    }

    public function getCaptureAction(): AbstractPaymentAction
    {
        return $this->captureAction;
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        return $this->notificationExtractor;
    }

    public function getPayParamsDecoder(): PayParamsDecoderInterface
    {
        return $this->payParamsDecoder;
    }

    public function getTransferNotificationVerifier(): PaymentTransferNotificationVerifierInterface
    {
        return $this->transferNotificationVerifier;
    }

    /**
     * A partial refund is a first class call of this interface: `refund_amount` is the amount that is
     * given back, not a flag.
     *
     * `mandate` is the agreement based deduction (`period_rule_params`), and the payout notification is
     * the ant message `alipay.fund.trans.order.changed`.
     *
     * @return list<PaymentCapability>
     */
    public function getDeclaredCapabilities(): array
    {
        return [
            PaymentCapability::PARTIAL_REFUND,
            PaymentCapability::MANDATE,
            PaymentCapability::TRANSFER_NOTIFICATION,
        ];
    }

    /**
     * @return list<PaymentConfigField>
     */
    public function getCredentials(): array
    {
        return [
            new PaymentConfigField(
                name: 'app_id',
                label: '支付宝应用 APPID',
                required: true,
            ),
            new PaymentConfigField(
                name: 'sandbox',
                label: '沙箱模式',
                type: PaymentConfigField::TYPE_BOOL,
                default: '0',
            ),
            new PaymentConfigField(
                name: 'private_key',
                label: '应用私钥',
                type: PaymentConfigField::TYPE_TEXTAREA,
                required: true,
                secret: true,
            ),
            new PaymentConfigField(
                name: 'alipay_public_key',
                label: '支付宝公钥',
                type: PaymentConfigField::TYPE_TEXTAREA,
                required: true,
            ),
            new PaymentConfigField(
                name: 'sign_type',
                label: '签名算法',
                type: PaymentConfigField::TYPE_SELECT,
                options: [self::SIGN_TYPE_RSA2 => self::SIGN_TYPE_RSA2],
                default: self::SIGN_TYPE_RSA2,
                helpText: '支付宝 SDK 目前只实现 RSA2（SHA256withRSA），因此这是唯一可选值。',
            ),
            new PaymentConfigField(
                name: 'gateway_url',
                label: '网关地址',
                default: self::DEFAULT_GATEWAY_URL,
                helpText: '留空使用默认网关；开启沙箱模式时自动使用沙箱网关。',
            ),
        ];
    }

    /**
     * @return list<PaymentChannel>
     */
    public function getChannels(): array
    {
        return [
            new PaymentChannel(
                technicalName: 'alipay_h5',
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_h5',
                flow: PaymentFlow::REDIRECT,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WAP],
                params: [$this->httpMethodField()],
            ),
            new PaymentChannel(
                technicalName: 'alipay_web',
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_web',
                flow: PaymentFlow::REDIRECT,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WEB],
                params: [$this->httpMethodField()],
            ),
            new PaymentChannel(
                technicalName: 'alipay_qr',
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_qr',
                flow: PaymentFlow::QRCODE,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WEB, PaymentChannel::DEVICE_WAP],
            ),
            new PaymentChannel(
                technicalName: 'alipay_app',
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_app',
                flow: PaymentFlow::SDK,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_APP],
            ),
            new PaymentChannel(
                technicalName: 'alipay_mini',
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_mini',
                flow: PaymentFlow::SDK,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_MINI],
                params: [
                    new PaymentConfigField(
                        name: 'op_app_id',
                        label: '小程序 APPID',
                        helpText: '拉起收银台的小程序 APPID，须在商家平台与 JSAPI 支付产品绑定；留空使用凭据中的应用 APPID。',
                    ),
                ],
                // the mini program payment needs the buyer it is created for, an app has to hand it in
                runtimeParams: ['buyer_id' => true],
            ),
        ];
    }

    /**
     * What alipay pays out to, as of the documented `alipay.fund.trans.uni.transfer` products.
     */
    public function getTransferRules(): PaymentTransferRules
    {
        return new PaymentTransferRules(
            identityTypes: ['alipay_user_id', 'alipay_logon_id', 'alipay_open_id', 'bankcard'],
            currencies: ['CNY'],
            requiredRecipientFields: [
                // a logon id carries no proof of ownership, so the name of the holder has to come along
                'alipay_logon_id' => [PaymentTransferRules::FIELD_HOLDER_NAME],
                'bankcard' => [PaymentTransferRules::FIELD_HOLDER_NAME, PaymentTransferRules::FIELD_BANK_INFO],
            ],
            // 1 is a company account, 2 a private one; alipay refuses everything else
            bankAccountTypes: ['1', '2'],
        );
    }

    /**
     * Alipay caps `period_rule_params.execute_time` at the 28th of the month and wants a new-style
     * charge initiated 48 hours ahead, inside the Beijing deduction window.
     */
    public function getCycleRules(): PaymentCycleRules
    {
        return new PaymentCycleRules(
            maxAnchorDayOfMonth: 28,
            leadTimeHours: 48,
            windowStartHour: 7,
            windowEndHour: 22,
            retryableFailureCodes: [
                'ACQ.BUYER_BALANCE_NOT_ENOUGH',
                'ACQ.BUYER_BANKCARD_BALANCE_NOT_ENOUGH',
                'ACQ.NO_PAYMENT_INSTRUMENTS_AVAILABLE',
                'ACQ.PAYMENT_FAIL',
            ],
            finalFailureCodes: [
                'ACQ.CYCLE_PAY_SINGLE_FEE_EXCEED',
                'ACQ.CYCLE_PAY_TOTAL_FEE_EXCEED',
                'ACQ.CYCLE_PAY_TOTAL_TIMES_EXCEED',
                'ACQ.CYCLE_PAY_DATE_NOT_MATCH',
                'ACQ.AGREEMENT_NOT_EXIST',
                'ACQ.AGREEMENT_INVALID',
                'ACQ.AGREEMENT_STATUS_NOT_NORMAL',
                'ACQ.MERCHANT_AGREEMENT_INVALID',
            ],
            // SYSTEM_ERROR may mean the charge went through, TRADE_HAS_SUCCESS is not a failure at all
            unknownFailureCodes: [
                'ACQ.SYSTEM_ERROR',
                'ACQ.TRADE_HAS_SUCCESS',
            ],
            maxRetries: 5,
        );
    }

    /**
     * `GET` redirects the payer, `POST` answers a self submitting form for a url that grew too long.
     */
    private function httpMethodField(): PaymentConfigField
    {
        return new PaymentConfigField(
            name: 'http_method',
            label: '跳转方式',
            type: PaymentConfigField::TYPE_SELECT,
            options: [
                'GET' => 'GET（直接跳转）',
                'POST' => 'POST（自提交表单）',
            ],
            default: 'GET',
            helpText: 'GET 直接把用户跳转到支付宝页面；POST 由平台返回自提交表单，参数过长时使用。',
        );
    }
}
