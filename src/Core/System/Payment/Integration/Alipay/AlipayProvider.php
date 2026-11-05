<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCloseAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayRefundAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Alipay\Notification\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Integration\Alipay\Request\AlipayPayParamsDecoder;
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
 * Alipay protocol actions and executable products, sharing merchant account credentials.
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
                technicalName: AlipayProducts::H5,
                scenes: [new PaymentScene('browser', 'wap', 'browser'), new PaymentScene('browser', 'wap', 'alipay')],
                providerTechnicalName: 'alipay',
                method: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_h5',
                interactions: [PaymentInteraction::REDIRECT],
                currencies: ['CNY'],
            ),
            new PaymentProduct(
                technicalName: AlipayProducts::WEB,
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_web',
                interactions: [PaymentInteraction::REDIRECT],
                method: 'alipay',
                currencies: ['CNY'],
                scenes: [new PaymentScene('browser', 'web', 'browser')],
            ),
            new PaymentProduct(
                technicalName: AlipayProducts::QR,
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_qr',
                interactions: [PaymentInteraction::QRCODE],
                method: 'alipay',
                currencies: ['CNY'],
                scenes: [new PaymentScene('browser', 'web', 'browser'), new PaymentScene('browser', 'wap', 'browser'), new PaymentScene('browser', 'wap', 'alipay')],
            ),
            new PaymentProduct(
                technicalName: AlipayProducts::APP,
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_app',
                interactions: [PaymentInteraction::SDK],
                method: 'alipay',
                currencies: ['CNY'],
                scenes: [new PaymentScene('native_app', 'app')],
            ),
            new PaymentProduct(
                technicalName: AlipayProducts::MINI,
                providerTechnicalName: 'alipay',
                label: 'sw-settings-payment-provider.channel.alipay_mini',
                interactions: [PaymentInteraction::SDK],
                method: 'alipay',
                currencies: ['CNY'],
                runtimeParams: ['buyer_id' => true],
                // the mini program payment needs the buyer it is created for, an app has to hand it in
                scenes: [new PaymentScene('mini_program', 'mini', 'alipay')],
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
     *
     * `ACQ.PAYMENT_FAIL` is the generic "the payment did not go through" the docs suggest retrying
     * exactly once: it hides a lot of permanent causes behind a temporary facade, so a second retry
     * burns the window far more often than it collects the money.
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
            singleRetryFailureCodes: [
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
            // SYSTEM_ERROR may mean the charge went through, TRADE_HAS_SUCCESS is not a failure at all;
            // both stay outside the two lists and therefore require a channel query before any retry
            maxRetries: 5,
        );
    }
}
