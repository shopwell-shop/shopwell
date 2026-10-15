<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Subscriber;

use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Opens the wallet of a customer as soon as the customer exists, so that every customer owns an
 * account from the start. Without this, a wallet only comes into existence with the first booking,
 * and the wallet screens of the administration cannot tell "no wallet yet" from "zero balance".
 *
 * This runs independently of the wallet program switch: the account is the container the ledger
 * books into, and the switch only decides whether the shop offers the program. A shop that keeps
 * the program off therefore does not end up with customers that own no account at all.
 *
 * Guests are treated like registered customers on purpose: the platform books their wallet payments
 * onto the very same kind of wallet, so leaving them out would only postpone the row until their
 * first order.
 *
 * Writes outside the live version are ignored, like everywhere else in checkout, and
 * {@see WalletService::provisionAccount()} leaves an existing wallet untouched, so the subscriber
 * is safe to run on every customer write.
 *
 * @internal
 */
#[Package('checkout')]
class WalletAccountProvisionSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WalletService $walletService)
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

            $this->walletService->provisionAccount($customerId, $context);
        }
    }
}
