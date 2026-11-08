<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@framework')]
class CurrencyException extends HttpException
{
    final public const INVALID_FIELD_VALUE_TYPE = 'SYSTEM__CURRENCY_INVALID_FIELD_VALUE_TYPE';
    final public const ISO_CODE_NOT_UNIQUE = 'SYSTEM__CURRENCY_ISO_CODE_NOT_UNIQUE';
    final public const UNSUPPORTED_CURRENCY = 'SYSTEM__CURRENCY_UNSUPPORTED';
    final public const INVALID_AMOUNT = 'SYSTEM__CURRENCY_INVALID_AMOUNT';

    public static function unsupportedCurrency(string $currency): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_CURRENCY,
            'The currency "{{ currency }}" has no supported minor-unit definition.',
            ['currency' => $currency],
        );
    }

    public static function invalidAmount(string $amount, string $currency): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_AMOUNT,
            'The amount "{{ amount }}" cannot be represented in integer minor units of {{ currency }}.',
            ['amount' => $amount, 'currency' => $currency],
        );
    }

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}',
            ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function isoCodeNotUnique(string $isoCode, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ISO_CODE_NOT_UNIQUE,
            'The ISO code "{{ isoCode }}" is already in use.',
            ['isoCode' => $isoCode],
            $e,
        );
    }
}
