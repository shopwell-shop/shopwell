<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * The codes keep their historical `LOYALTY_` prefix on purpose: they are answered to the storefront and
 * the administration, which switch on them, so renaming one is a contract change and not a move.
 */
#[Package('checkout')]
class MembershipException extends HttpException
{
    final public const PLAN_NOT_FOUND_CODE = 'CHECKOUT__LOYALTY_PLAN_NOT_FOUND';

    final public const PLAN_NOT_PURCHASABLE_CODE = 'CHECKOUT__LOYALTY_PLAN_NOT_PURCHASABLE';

    final public const SUBSCRIPTION_NOT_FOUND_CODE = 'CHECKOUT__LOYALTY_SUBSCRIPTION_NOT_FOUND';

    final public const SUBSCRIPTION_WITHOUT_TIER_CODE = 'CHECKOUT__LOYALTY_SUBSCRIPTION_WITHOUT_TIER';

    final public const SUBSCRIPTION_WITHOUT_ACCOUNT_CODE = 'CHECKOUT__LOYALTY_SUBSCRIPTION_WITHOUT_ACCOUNT';

    final public const INVALID_SUBSCRIPTION_DURATION_CODE = 'CHECKOUT__LOYALTY_INVALID_SUBSCRIPTION_DURATION';

    /**
     * A membership that lasts no day at all is a typo, not a grant.
     */
    public static function invalidSubscriptionDuration(int $durationDays): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SUBSCRIPTION_DURATION_CODE,
            'A membership has to last at least one day, got "{{ durationDays }}".',
            ['durationDays' => $durationDays]
        );
    }

    public static function subscriptionNotFound(string $subscriptionId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SUBSCRIPTION_NOT_FOUND_CODE,
            'The membership subscription "{{ subscriptionId }}" does not exist.',
            ['subscriptionId' => $subscriptionId]
        );
    }

    /**
     * A purchase whose tier was deleted before it was paid cannot become a membership: granting nothing
     * and taking the money anyway would be worse than failing the settlement.
     */
    public static function subscriptionWithoutTier(string $purchaseId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SUBSCRIPTION_WITHOUT_TIER_CODE,
            'The membership purchase "{{ purchaseId }}" has no tier to grant.',
            ['purchaseId' => $purchaseId]
        );
    }

    /**
     * A membership is projected onto the loyalty account of the customer; without that account there is
     * nothing to project onto, and silently skipping the projection would leave a paid membership that
     * no page can see.
     */
    public static function subscriptionWithoutAccount(string $customerId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SUBSCRIPTION_WITHOUT_ACCOUNT_CODE,
            'The customer "{{ customerId }}" has no loyalty account to carry a membership.',
            ['customerId' => $customerId]
        );
    }

    public static function planNotFound(string $planId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PLAN_NOT_FOUND_CODE,
            'The membership plan "{{ planId }}" does not exist.',
            ['planId' => $planId]
        );
    }

    public static function planNotPurchasable(string $planId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PLAN_NOT_PURCHASABLE_CODE,
            'The membership plan "{{ planId }}" cannot be bought in this currency.',
            ['planId' => $planId]
        );
    }
}
