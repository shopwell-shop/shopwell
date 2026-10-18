<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The verifiers the platform ships, collected from the services tagged as
 * `shopwell.payment.transfer_notification_verifier`.
 *
 * The provider is the key because the interface that signs a notification is the interface that was
 * addressed, and the notify url names it. An interface of an extension brings its own verifier and is
 * reachable through this registry without a change here.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferNotificationVerifierRegistry
{
    /**
     * @var array<string, PaymentTransferNotificationVerifierInterface>
     */
    private array $verifiers = [];

    /**
     * @param iterable<PaymentTransferNotificationVerifierInterface> $verifiers
     */
    public function __construct(iterable $verifiers)
    {
        foreach ($verifiers as $verifier) {
            $this->verifiers[$verifier->provider()] = $verifier;
        }
    }

    public function has(string $provider): bool
    {
        return isset($this->verifiers[$provider]);
    }

    /**
     * @throws PaymentException
     */
    public function get(string $provider): PaymentTransferNotificationVerifierInterface
    {
        return $this->verifiers[$provider] ?? throw PaymentException::transferNotificationProviderUnknown($provider);
    }
}
