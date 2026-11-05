<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCaptureAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCloseAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatRefundAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Wechat\Product\WechatProducts;
use Shopwell\Core\System\Payment\Integration\Wechat\Request\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;
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
     * The translated merchant and channel fields, read with the platform plugin-config reader.
     */
    public function getConfigFile(): string
    {
        return __DIR__ . '/Resources/config/payment-provider.xml';
    }

    /**
     * @return list<PaymentProduct>
     */
    public function getProducts(): array
    {
        return [
            new PaymentProduct(
                technicalName: WechatProducts::H5,
                scenes: [new PaymentScene('browser', 'wap', 'browser')],
                providerTechnicalName: 'wechat',
                method: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_h5',
                interactions: [PaymentInteraction::REDIRECT],
                currencies: ['CNY'],
            ),
            new PaymentProduct(
                technicalName: WechatProducts::NATIVE,
                scenes: [new PaymentScene('browser', 'web', 'browser')],
                providerTechnicalName: 'wechat',
                method: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_native',
                interactions: [PaymentInteraction::QRCODE],
                currencies: ['CNY'],
            ),
            new PaymentProduct(
                technicalName: WechatProducts::JSAPI,
                scenes: [new PaymentScene('browser', 'wap', 'wechat')],
                providerTechnicalName: 'wechat',
                method: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_jsapi',
                interactions: [PaymentInteraction::SDK],
                currencies: ['CNY'],

                // the openid only exists inside the public account that issues it
                runtimeParams: ['openid' => true],
            ),
            new PaymentProduct(
                technicalName: WechatProducts::MINI,
                scenes: [new PaymentScene('mini_program', 'mini', 'wechat')],
                providerTechnicalName: 'wechat',
                method: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_mini',
                interactions: [PaymentInteraction::SDK],
                currencies: ['CNY'],
                runtimeParams: ['openid' => true],
            ),
            new PaymentProduct(
                technicalName: WechatProducts::APP,
                scenes: [new PaymentScene('native_app', 'app')],
                providerTechnicalName: 'wechat',
                method: 'wechat',
                label: 'sw-settings-payment-provider.channel.wechat_app',
                interactions: [PaymentInteraction::SDK],
                currencies: ['CNY'],
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
            // failure at all and ORDERCLOSED needs a fresh out trade number instead of a retry; they
            // stay outside the two lists and therefore require a channel query before any retry
            maxRetries: 5,
        );
    }
}
