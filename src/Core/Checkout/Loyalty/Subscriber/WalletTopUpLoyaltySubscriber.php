<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Subscriber;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Wallet\Event\WalletTopUpCreditedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Pays the top-up bonus when money lands on the wallet.
 *
 * The wallet does not know about the points program and must not: it announces that a top-up was
 * credited and this listener decides what that is worth. The dependency points one way only - the
 * points program reads wallet facts, never the other way round - so a shop without a loyalty program
 * runs the wallet unchanged.
 *
 * The bonus is points and nothing else. Tier points stay untouched: a tier says what a customer
 * bought over time, and moving money into the shop is not buying. It also cannot be a way to buy a
 * tier.
 *
 * A failure here must not cost the customer their money. The credit is already committed when this
 * runs, so the exception is logged instead of being thrown into the payment notification: the
 * gateway would report the payment again, the settlement would answer "already settled", and the
 * bonus would be lost without a trace. The booking key is the top-up id, which makes a replay safe.
 *
 * @internal
 */
#[Package('checkout')]
class WalletTopUpLoyaltySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly LoyaltySettings $settings,
        private readonly LoyaltyBenefitResolver $benefits,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WalletTopUpCreditedEvent::class => 'onTopUpCredited',
        ];
    }

    public function onTopUpCredited(WalletTopUpCreditedEvent $event): void
    {
        $context = $event->getContext();

        // no sales channel: the wallet belongs to the customer, not to the channel the money came in through
        if (!$this->settings->isEnabled()) {
            return;
        }

        $perUnit = $this->settings->topUpPointsPerCurrencyUnit();

        if ($perUnit <= 0) {
            return;
        }

        $multiplier = $this->benefits->earnMultiplier($event->getCustomerId(), $context);

        // the same arithmetic the completed order uses, so a member sees one earning rule and not two.
        // It stays in the currency's own unit instead of minor units because the tier multiplier is a
        // float anyway - what has to be right is the floor, not the last bit of the multiplication
        $points = (int) floor($event->getAmount() * $perUnit * $multiplier);

        if ($points <= 0) {
            return;
        }

        try {
            $this->loyaltyPointService->award(
                $event->getCustomerId(),
                $points,
                null,
                $event->getTopUpId() . '-bonus',
                $context
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Could not book the points of a wallet top-up.', [
                'topUpId' => $event->getTopUpId(),
                'customerId' => $event->getCustomerId(),
                'currency' => $event->getCurrency(),
                'points' => $points,
                'exception' => $exception,
            ]);
        }
    }
}
