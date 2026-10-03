<?php declare(strict_types=1);

namespace Shopwell\Tests\Examples;

use Shopwell\Core\Checkout\Customer\Extension\CustomerRecoveryIsExpiredRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decides whether a recovery hash is expired from your own store instead of
 * the core customer_recovery lookup.
 */
readonly class CustomerRecoveryIsExpiredRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRecoveryIsExpiredRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(CustomerRecoveryIsExpiredRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data (the recovery hash), $event->context

        $event->result = new CustomerRecoveryIsExpiredResponse(false);

        // stop propagation so the core expiry check is skipped
        $event->stopPropagation();
    }
}
