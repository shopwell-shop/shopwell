<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

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
 * The wechat pay interface of the platform.
 *
 * One merchant id is one set of credentials and every channel below is charged on it. The `appid` is
 * deliberately **not** a credential: every product is bound to a different appid (a public account, a
 * mini program, a native app), while all of them share the merchant id and its keys - so the appid is a
 * parameter of the channel.
 *
 * The declaration used to be `Provider/Resources/config/wechat.xml`.
 *
 * @internal
 */
#[Package('framework')]
final class WechatProvider extends AbstractPaymentProvider
{
    /**
     * The snippet key of the name the administration shows.
     */
    final public const LABEL = 'sw-settings-payment-provider.provider.wechat';

    /**
     * From 2000 yuan on wechat demands the name of the holder, encrypted with the platform key. The
     * amount is in the smallest currency unit.
     */
    final public const HOLDER_NAME_THRESHOLD = 200000;

    /**
     * Wechat closes a payout the recipient never confirmed after 24 hours and refunds it.
     */
    final public const CONFIRM_WINDOW = '+24 hours';

    public function __construct(
        private readonly WechatCaptureAction $captureAction,
        private readonly WechatRefundAction $refundAction,
        private readonly WechatNotifyAction $notifyAction,
        private readonly WechatQueryAction $queryAction,
        private readonly WechatCloseAction $closeAction,
        private readonly WechatTransferAction $transferAction,
        private readonly WechatTransferQueryAction $transferQueryAction,
        private readonly WechatNotificationExtractor $notificationExtractor,
        private readonly WechatPayParamsDecoder $payParamsDecoder,
        private readonly WechatTransferNotificationVerifier $transferNotificationVerifier,
    ) {
    }

    public function getName(): string
    {
        return 'wechat';
    }

    public function getLabel(): string
    {
        return self::LABEL;
    }

    public function getApiClass(): string
    {
        return WechatApi::class;
    }

    /**
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
     * The refund amount is the amount that is given back, so a partial refund is a normal call.
     *
     * `mandate` is the contract based deduction (`contract_id`), and the payout notification is the
     * transfer callback of the merchant platform.
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
                name: 'mch_id',
                label: '商户号',
                required: true,
            ),
            new PaymentConfigField(
                name: 'mch_serial',
                label: '商户证书序列号',
                required: true,
            ),
            new PaymentConfigField(
                name: 'mch_private_key',
                label: '商户私钥',
                type: PaymentConfigField::TYPE_TEXTAREA,
                required: true,
                secret: true,
            ),
            new PaymentConfigField(
                name: 'apiv3_key',
                label: 'APIv3 密钥',
                type: PaymentConfigField::TYPE_PASSWORD,
                required: true,
                secret: true,
            ),
            new PaymentConfigField(
                name: 'platform_serial',
                label: '微信支付平台证书序列号',
            ),
            new PaymentConfigField(
                name: 'platform_cert',
                label: '微信支付平台证书',
                type: PaymentConfigField::TYPE_TEXTAREA,
            ),
            new PaymentConfigField(
                name: 'platform_certs',
                label: '微信支付平台证书（轮换用）',
                type: PaymentConfigField::TYPE_TEXTAREA,
                helpText: 'JSON 对象，键为证书序列号、值为证书内容；平台证书轮换时可同时配置新旧两张，避免验签中断。',
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
                technicalName: 'wechat_h5',
                providerTechnicalName: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_h5',
                flow: PaymentFlow::REDIRECT,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WAP],
                params: [
                    new PaymentConfigField(
                        name: 'appid',
                        label: '公众号 / APP 的 appid',
                        required: true,
                    ),
                    new PaymentConfigField(
                        name: 'h5_type',
                        label: 'H5 场景类型',
                        type: PaymentConfigField::TYPE_SELECT,
                        options: [
                            'Wap' => 'Wap（手机浏览器）',
                            'iOS' => 'iOS（iOS 应用内）',
                            'Android' => 'Android（Android 应用内）',
                        ],
                        default: 'Wap',
                        helpText: '微信按场景类型做风险控制，须与用户实际终端一致：手机浏览器选 Wap，内嵌在 iOS / Android 应用里则选 iOS / Android。',
                    ),
                ],
            ),
            new PaymentChannel(
                technicalName: 'wechat_native',
                providerTechnicalName: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_native',
                flow: PaymentFlow::QRCODE,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WEB],
                params: [
                    new PaymentConfigField(
                        name: 'appid',
                        label: '公众号 / APP 的 appid',
                        required: true,
                    ),
                ],
            ),
            new PaymentChannel(
                technicalName: 'wechat_jsapi',
                providerTechnicalName: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_jsapi',
                flow: PaymentFlow::SDK,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_WAP],
                params: [
                    new PaymentConfigField(
                        name: 'appid',
                        label: '公众号的 appid',
                        required: true,
                    ),
                ],
                // the openid only exists inside the public account that issues it
                runtimeParams: ['openid' => true],
            ),
            new PaymentChannel(
                technicalName: 'wechat_mini',
                providerTechnicalName: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_mini',
                flow: PaymentFlow::SDK,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_MINI],
                params: [
                    new PaymentConfigField(
                        name: 'appid',
                        label: '小程序的 appid',
                        required: true,
                    ),
                ],
                runtimeParams: ['openid' => true],
            ),
            new PaymentChannel(
                technicalName: 'wechat_app',
                providerTechnicalName: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_app',
                flow: PaymentFlow::SDK,
                currencies: ['CNY'],
                devices: [PaymentChannel::DEVICE_APP],
                params: [
                    new PaymentConfigField(
                        name: 'appid',
                        label: 'APP 的 appid',
                        required: true,
                    ),
                ],
            ),
        ];
    }

    /**
     * Wechat pays out to the openid of the very app that issued it and to nothing else.
     */
    public function getTransferRules(): PaymentTransferRules
    {
        return new PaymentTransferRules(
            identityTypes: ['wechat_open_id'],
            currencies: ['CNY'],
            // the name of the holder only becomes mandatory for larger payouts and is then a field of
            // the recipient, not of the call
            holderNameThreshold: self::HOLDER_NAME_THRESHOLD,
            confirmWindow: self::CONFIRM_WINDOW,
        );
    }

    /**
     * Wechat puts no cap on the day of the month and needs no lead time; the deduction window is the
     * same Beijing window alipay uses.
     */
    public function getCycleRules(): PaymentCycleRules
    {
        return new PaymentCycleRules(
            maxAnchorDayOfMonth: null,
            leadTimeHours: 0,
            windowStartHour: 7,
            windowEndHour: 22,
            retryableFailureCodes: [
                'NOTENOUGH',
                'BANKERROR',
                'FREQUENCY_LIMITED',
                // stops the day, not the cycle - tomorrow's window is legal again
                'RULELIMIT',
            ],
            finalFailureCodes: [
                'CONTRACT_NOT_EXIST',
                'CONTRACTERROR',
                'ACCOUNTERROR',
                'USER_ACCOUNT_ABNORMAL',
                'USER_NOT_EXIST',
                'USER_ERROR',
            ],
            // PROCESSING forbids a second charge while the first is in flight, ORDERPAID is not a
            // failure at all and ORDERCLOSED needs a fresh out trade number instead of a retry
            unknownFailureCodes: [
                'SYSTEMERROR',
                'ORDER_ACCEPTED',
                'ORDERPAID',
                'ORDERCLOSED',
            ],
            maxRetries: 5,
        );
    }
}
