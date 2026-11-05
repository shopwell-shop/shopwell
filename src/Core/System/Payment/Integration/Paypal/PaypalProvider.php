<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalCaptureAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalNotifyAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalQueryAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalRefundAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalTransferAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Action\PaypalTransferQueryAction;
use Shopwell\Core\System\Payment\Integration\Paypal\Api\PaypalApi;
use Shopwell\Core\System\Payment\Integration\Paypal\Notification\PaypalNotificationExtractor;
use Shopwell\Core\System\Payment\Integration\Paypal\Notification\PaypalTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Integration\Paypal\Product\PaypalProducts;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The paypal interface of the platform.
 *
 * Paypal has no close action: an order that was created but never approved expires on its own, and the
 * platform refuses the request with `PAYMENT__CHANNEL_NOT_SUPPORTED` instead of pretending the channel
 * broke. It also ships no pay params decoder - the capture records the raw body of the create order
 * call, where the approval url is only one of the links, so a returning payer is captured again.
 *
 * The declaration used to be `Provider/Resources/config/paypal.xml`.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalProvider extends AbstractPaymentProvider
{
    /**
     * The snippet key of the name the administration shows.
     */
    final public const LABEL = 'sw-settings-payment-provider.provider.paypal';

    public function __construct(
        private readonly PaypalCaptureAction $captureAction,
        private readonly PaypalRefundAction $refundAction,
        private readonly PaypalNotifyAction $notifyAction,
        private readonly PaypalQueryAction $queryAction,
        private readonly PaypalTransferAction $transferAction,
        private readonly PaypalTransferQueryAction $transferQueryAction,
        private readonly PaypalNotificationExtractor $notificationExtractor,
        private readonly PaypalTransferNotificationVerifier $transferNotificationVerifier,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function getLabel(): string
    {
        return self::LABEL;
    }

    public function getApiClass(): string
    {
        return PaypalApi::class;
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
            $this->transferAction,
            $this->transferQueryAction,
        ];
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        return $this->notificationExtractor;
    }

    /**
     * The api fetches the certificate a webhook is signed with, so it needs the http client.
     *
     * @return list<mixed>
     */
    public function getApiArguments(): array
    {
        return [$this->httpClient];
    }

    public function getTransferNotificationVerifier(): PaymentTransferNotificationVerifierInterface
    {
        return $this->transferNotificationVerifier;
    }

    /**
     * The refund carries an amount and a currency, so a partial refund is a normal call.
     *
     * @return list<PaymentCapability>
     */
    public function getDeclaredCapabilities(): array
    {
        return [
            PaymentCapability::PARTIAL_REFUND,
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
     * Paypal declares no currencies and no devices: what the merchant account can be paid in is a
     * property of that account, which the checkout of paypal decides itself.
     *
     * @return list<PaymentProduct>
     */
    public function getProducts(): array
    {
        return [
            new PaymentProduct(
                technicalName: PaypalProducts::CHECKOUT,
                scenes: [new PaymentScene('browser', 'web'), new PaymentScene('browser', 'wap'), new PaymentScene('native_app', 'app')],
                providerTechnicalName: 'paypal',
                method: 'paypal',
                label: 'sw-settings-payment-provider.channel.paypal_checkout',
                interactions: [PaymentInteraction::REDIRECT],
            ),
        ];
    }

    /**
     * Paypal pays out to a paypal account - by the email, the phone number or the payer id of it. The
     * receiving account decides the currency, so the platform declares none.
     */
    public function getTransferRules(): PaymentTransferRules
    {
        return new PaymentTransferRules(
            identityTypes: ['paypal_email', 'paypal_phone', 'paypal_payer_id'],
        );
    }
}
