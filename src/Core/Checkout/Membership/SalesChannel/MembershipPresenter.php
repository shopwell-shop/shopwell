<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\SalesChannel;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierBenefits;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseEntity;
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
            'tier' => $tier instanceof LoyaltyTierEntity ? $this->tier($tier) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchase(MembershipPurchaseEntity $purchase): array
    {
        return [
            'id' => $purchase->getId(),
            'planName' => $purchase->getPlanName(),
            'amount' => $purchase->getAmount(),
            'currency' => $purchase->getCurrency(),
            'durationDays' => $purchase->getDurationDays(),
            'state' => $purchase->getState(),
            'startsAt' => $purchase->getStartsAt()?->format(\DATE_ATOM),
            'expiresAt' => $purchase->getExpiresAt()?->format(\DATE_ATOM),
            'createdAt' => $purchase->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * The tier of the plan, with what it grants.
     *
     * The benefits are printed from the association the plan service loaded - a presenter maps what it
     * was given and never asks the database itself, so the page keeps costing one query per plan.
     *
     * @return array<string, mixed>
     */
    private function tier(LoyaltyTierEntity $tier): array
    {
        $benefits = $tier->getBenefits();

        return [
            'id' => $tier->getId(),
            'name' => $tier->getName(),
            'level' => $tier->getLevel(),
            'benefits' => $benefits === null ? [] : LoyaltyTierBenefits::of($benefits)->all(),
        ];
    }
}
