<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Uuid;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopwell\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class UuidException extends HttpException
{
    public static function invalidUuid(string $uuid): ShopwellHttpException
    {
        return new InvalidUuidException($uuid);
    }

    public static function invalidUuidLength(int $length, string $hex): ShopwellHttpException
    {
        return new InvalidUuidLengthException($length, $hex);
    }
}
