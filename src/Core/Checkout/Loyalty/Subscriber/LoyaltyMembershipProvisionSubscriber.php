<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Subscriber;

use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Opens the membership of a customer as soon as the customer exists, so that every customer owns a
 * membership from the start. Without this, a membership only comes into existence with the first
 * booking, and the point views of the administration cannot tell "no membership yet" from
 * "zero points".
 *
 * This runs independently of the loyalty program switch: the membership is the container the ledger
 * books into, and the switch only decides whether the shop offers the program. A shop that keeps
 * the program off therefore does not end up with customers that own no membership at all.
 *
 * Guests are treated like registered customers on purpose: the platform books the points of their
 * orders onto the very same kind of membership, so leaving them out would only postpone the row
 * until their first order.
 *
 * Writes outside the live version are ignored, like everywhere else in checkout, and
 * {@see LoyaltyPointService::provisionMembership()} leaves an existing membership untouched, so the
 * subscriber is safe to run on every customer write.
 *
 * @internal
 */
#[Package('checkout')]
class LoyaltyMembershipProvisionSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly LoyaltyPointService $loyaltyPointService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        foreach ($event->getIds() as $customerId) {
            if (!\is_string($customerId)) {
                continue;
            }

            $this->loyaltyPointService->provisionMembership($customerId, $context);
        }
    }
}
