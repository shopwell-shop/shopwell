<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class LoyaltyException extends HttpException
{
    final public const LOYALTY_INVALID_POINTS_CODE = 'CHECKOUT__LOYALTY_INVALID_POINTS';
    final public const LOYALTY_INSUFFICIENT_POINTS_CODE = 'CHECKOUT__LOYALTY_INSUFFICIENT_POINTS';
    final public const LOYALTY_BELOW_MINIMUM_POINTS_CODE = 'CHECKOUT__LOYALTY_BELOW_MINIMUM_POINTS';
    final public const LOYALTY_EXCEEDS_MAX_REDEEMABLE_CODE = 'CHECKOUT__LOYALTY_EXCEEDS_MAX_REDEEMABLE';
    final public const LOYALTY_PLAN_NOT_FOUND_CODE = 'CHECKOUT__LOYALTY_PLAN_NOT_FOUND';
    final public const LOYALTY_PLAN_NOT_PURCHASABLE_CODE = 'CHECKOUT__LOYALTY_PLAN_NOT_PURCHASABLE';
    final public const LOYALTY_TIER_NOT_FOUND_CODE = 'CHECKOUT__LOYALTY_TIER_NOT_FOUND';
    final public const LOYALTY_INVALID_MEMBERSHIP_DURATION_CODE = 'CHECKOUT__LOYALTY_INVALID_MEMBERSHIP_DURATION';

    public static function invalidPoints(int $points): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_INVALID_POINTS_CODE,
            'The number of points "{{ points }}" is invalid. Points must be a positive integer.',
            ['points' => $points]
        );
    }

    public static function insufficientPoints(string $customerId, int $requested, int $available): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_INSUFFICIENT_POINTS_CODE,
            'Customer "{{ customerId }}" has {{ available }} points available, but {{ requested }} points were requested.',
            ['customerId' => $customerId, 'requested' => $requested, 'available' => $available]
        );
    }

    public static function belowMinimumPoints(int $requested, int $minimum): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_BELOW_MINIMUM_POINTS_CODE,
            'At least {{ minimum }} points must be redeemed, but only {{ requested }} points were requested.',
            ['minimum' => $minimum, 'requested' => $requested]
        );
    }

    public static function exceedsMaxRedeemablePercent(float $maxPercent): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_EXCEEDS_MAX_REDEEMABLE_CODE,
            'The requested points exceed the maximum of {{ maxPercent }}% of the order total that may be paid with points.',
            ['maxPercent' => $maxPercent]
        );
    }

    public static function planNotFound(string $planId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LOYALTY_PLAN_NOT_FOUND_CODE,
            'The membership plan "{{ planId }}" does not exist.',
            ['planId' => $planId]
        );
    }

    public static function planNotPurchasable(string $planId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_PLAN_NOT_PURCHASABLE_CODE,
            'The membership plan "{{ planId }}" cannot be bought in this currency.',
            ['planId' => $planId]
        );
    }

    public static function tierNotFound(string $tierId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LOYALTY_TIER_NOT_FOUND_CODE,
            'The membership tier "{{ tierId }}" does not exist.',
            ['tierId' => $tierId]
        );
    }

    public static function invalidMembershipDuration(int $durationDays): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOYALTY_INVALID_MEMBERSHIP_DURATION_CODE,
            'The membership duration "{{ durationDays }}" is invalid. A membership lasts at least one day.',
            ['durationDays' => $durationDays]
        );
    }
}
