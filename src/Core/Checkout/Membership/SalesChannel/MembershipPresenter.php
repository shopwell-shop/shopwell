<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\SalesChannel;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * The shapes the account page reads for the membership offer and its history.
 *
 * @final
 */
#[Package('checkout')]
class MembershipPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function plan(MembershipPlanEntity $plan): array
    {
        $tier = $plan->getLoyaltyTier();

        return [
            'id' => $plan->getId(),
            'technicalName' => $plan->getTechnicalName(),
            'name' => $plan->getName(),
            'description' => $plan->getDescription(),
            'price' => $plan->getPrice(),
            'durationDays' => $plan->getDurationDays(),
            'currencyId' => $plan->getCurrencyId(),
            'tier' => $tier instanceof LoyaltyTierEntity ? [
                'id' => $tier->getId(),
                'name' => $tier->getName(),
                'level' => $tier->getLevel(),
                'pointsMultiplier' => $tier->getPointsMultiplier(),
                'discountPercent' => $tier->getDiscountPercent(),
                'freeShipping' => $tier->getFreeShipping(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function order(MembershipOrderEntity $order): array
    {
        return [
            'id' => $order->getId(),
            'planName' => $order->getPlanName(),
            'amount' => $order->getAmount(),
            'currency' => $order->getCurrency(),
            'durationDays' => $order->getDurationDays(),
            'state' => $order->getState(),
            'startsAt' => $order->getStartsAt()?->format(\DATE_ATOM),
            'expiresAt' => $order->getExpiresAt()?->format(\DATE_ATOM),
            'createdAt' => $order->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }
}
