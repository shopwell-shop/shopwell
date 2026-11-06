<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Request;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;

/**
 * The one interpretation of wechat's trade state, shared by queries and notifications.
 *
 * @internal
 */
#[Package('framework')]
final class WechatPaymentStatus
{
    private const STATES = [
        'SUCCESS' => QueryResult::STATUS_PAID,
        'REFUND' => QueryResult::STATUS_PAID,
        'CLOSED' => QueryResult::STATUS_CLOSED,
        'PAYERROR' => QueryResult::STATUS_FAILED,
    ];

    public static function query(string $status): string
    {
        return self::STATES[$status] ?? QueryResult::STATUS_PENDING;
    }

    public static function isPaid(string $status): bool
    {
        return self::query($status) === QueryResult::STATUS_PAID;
    }

    public static function isSuccessfulNotification(string $status): bool
    {
        return $status === 'SUCCESS';
    }
}
