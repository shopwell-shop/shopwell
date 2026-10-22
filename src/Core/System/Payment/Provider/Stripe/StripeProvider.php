<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * The stripe interface of the platform.
 *
 * Stripe has no close action - a payment intent that was created but never confirmed expires on its own
 * - and ships no pay params decoder: the checkout opens an intent whose client secret is signed per
 * call for the page that consumes it, so there is no browser parameter that survives a reopen.
 *
 * Payouts go to a connected account and not to a bank card, and the account has to finish its
 * onboarding before money may be sent to it - that is what `requiresOnboarding` declares.
 *
 * The declaration used to be `Provider/Resources/config/stripe.xml`.
 *
 * @internal
 */
#[Package('framework')]
final class StripeProvider extends AbstractPaymentProvider
{
    /**
     * The snippet key of the name the administration shows.
     */
    final public const LABEL = 'sw-settings-payment-provider.provider.stripe';

    public function __construct(
        private readonly StripeCaptureAction $captureAction,
        private readonly StripeRefundAction $refundAction,
        private readonly StripeNotifyAction $notifyAction,
        private readonly StripeQueryAction $queryAction,
        private readonly StripeTransferAction $transferAction,
        private readonly StripeTransferQueryAction $transferQueryAction,
        private readonly StripeNotificationExtractor $notificationExtractor,
        private readonly StripeTransferNotificationVerifier $transferNotificationVerifier,
    ) {
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function getLabel(): string
    {
        return self::LABEL;
    }

    public function getApiClass(): string
    {
        return StripeApi::class;
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

    public function getCaptureAction(): AbstractPaymentAction
    {
        return $this->captureAction;
    }

    public function getNotificationExtractor(): PaymentNotificationExtractorInterface
    {
        return $this->notificationExtractor;
    }

    public function getTransferNotificationVerifier(): PaymentTransferNotificationVerifierInterface
    {
        return $this->transferNotificationVerifier;
    }

    /**
     * A stripe refund carries an amount in the smallest currency unit, so a partial refund is a normal
     * call.
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
     * @return list<PaymentConfigField>
     */
    public function getCredentials(): array
    {
        return [
            new PaymentConfigField(
                name: 'secret_key',
                label: 'Secret Key',
                type: PaymentConfigField::TYPE_PASSWORD,
                required: true,
                secret: true,
            ),
            new PaymentConfigField(
                name: 'publishable_key',
                label: 'Publishable Key',
            ),
            new PaymentConfigField(
                name: 'webhook_secret',
                label: 'Webhook 签名密钥',
                type: PaymentConfigField::TYPE_PASSWORD,
                secret: true,
            ),
            new PaymentConfigField(
                name: 'api_version',
                label: 'API 版本',
            ),
        ];
    }

    /**
     * Stripe declares no currencies and no devices: a payment intent is created in the currency the
     * caller asks for, and the checkout is a hosted page that works everywhere.
     *
     * @return list<PaymentChannel>
     */
    public function getChannels(): array
    {
        return [
            new PaymentChannel(
                technicalName: 'stripe_checkout',
                providerTechnicalName: 'stripe',
                label: 'sw-settings-payment-provider.channel.stripe_checkout',
                flow: PaymentFlow::REDIRECT,
            ),
        ];
    }

    /**
     * Stripe pays out to a connected account, and only once that account finished its onboarding - the
     * `account.updated` webhook mirrors `payouts_enabled`, which is what makes a recipient payable.
     */
    public function getTransferRules(): PaymentTransferRules
    {
        return new PaymentTransferRules(
            identityTypes: ['stripe_account'],
            requiresOnboarding: true,
        );
    }
}
