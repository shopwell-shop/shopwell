<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Request;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;

/**
 * The one interpretation of alipay's trade status, shared by queries and notifications.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayPaymentStatus
{
    private const STATES = [
        'TRADE_SUCCESS' => QueryResult::STATUS_PAID,
        'TRADE_FINISHED' => QueryResult::STATUS_PAID,
        'TRADE_CLOSED' => QueryResult::STATUS_CLOSED,
    ];

    public static function query(?string $status): string
    {
        if ($status === null) {
            return QueryResult::STATUS_ABSENT;
        }

        return self::STATES[$status] ?? QueryResult::STATUS_PENDING;
    }

    public static function isPaid(string $status): bool
    {
        return (self::STATES[$status] ?? null) === QueryResult::STATUS_PAID;
    }
}
