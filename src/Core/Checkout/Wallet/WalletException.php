<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class WalletException extends HttpException
{
    final public const WALLET_INVALID_AMOUNT_CODE = 'CHECKOUT__WALLET_INVALID_AMOUNT';
    final public const WALLET_INSUFFICIENT_BALANCE_CODE = 'CHECKOUT__WALLET_INSUFFICIENT_BALANCE';
    final public const WALLET_BELOW_MINIMUM_TOP_UP_CODE = 'CHECKOUT__WALLET_BELOW_MINIMUM_TOP_UP';
    final public const WALLET_EXCEEDS_MAX_PAYMENT_CODE = 'CHECKOUT__WALLET_EXCEEDS_MAX_PAYMENT';

    public static function invalidAmount(float $amount): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::WALLET_INVALID_AMOUNT_CODE,
            'The amount "{{ amount }}" is invalid. The amount must be greater than zero.',
            ['amount' => $amount]
        );
    }

    public static function insufficientBalance(string $customerId, string $currencyId, float $requested, float $available): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::WALLET_INSUFFICIENT_BALANCE_CODE,
            'The wallet of customer "{{ customerId }}" in currency "{{ currencyId }}" holds {{ available }}, but {{ requested }} was requested.',
            ['customerId' => $customerId, 'currencyId' => $currencyId, 'requested' => $requested, 'available' => $available]
        );
    }

    public static function belowMinimumTopUp(float $requested, float $minimum): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::WALLET_BELOW_MINIMUM_TOP_UP_CODE,
            'A top-up of at least {{ minimum }} is required, but only {{ requested }} was given.',
            ['minimum' => $minimum, 'requested' => $requested]
        );
    }

    public static function exceedsMaxPaymentPercent(float $maxPercent): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::WALLET_EXCEEDS_MAX_PAYMENT_CODE,
            'The requested wallet payment exceeds the maximum of {{ maxPercent }}% of the order total that may be paid with the wallet.',
            ['maxPercent' => $maxPercent]
        );
    }
}
