<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Paypal\Request;

use PaypalServerSdkLib\Models\CaptureStatus;
use PaypalServerSdkLib\Models\OrderStatus;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;

/**
 * Paypal capture and order statuses are different vocabularies and stay separate here.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalPaymentStatus
{
    public static function capture(string $status): string
    {
        return match ($status) {
            CaptureStatus::COMPLETED, CaptureStatus::PARTIALLY_REFUNDED, CaptureStatus::REFUNDED => QueryResult::STATUS_PAID,
            CaptureStatus::DECLINED, CaptureStatus::FAILED => QueryResult::STATUS_FAILED,
            default => QueryResult::STATUS_PENDING,
        };
    }

    public static function order(string $status): string
    {
        return $status === OrderStatus::VOIDED ? QueryResult::STATUS_CLOSED : QueryResult::STATUS_PENDING;
    }
}
