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
}
