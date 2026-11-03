<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Stripe\Request;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\QueryResult;

/**
 * The status interpretation of stripe's intent and checkout session resources.
 *
 * @internal
 */
#[Package('framework')]
final class StripePaymentStatus
{
    public static function intent(string $status): string
    {
        return match ($status) {
            'succeeded' => QueryResult::STATUS_PAID,
            'canceled' => QueryResult::STATUS_CLOSED,
            default => QueryResult::STATUS_PENDING,
        };
    }

    public static function session(string $status, string $paymentStatus): string
    {
        if ($status === 'expired') {
            return QueryResult::STATUS_CLOSED;
        }

        // a completed session may still settle asynchronously, only paid means money was received
        return $status === 'complete' && $paymentStatus === 'paid'
            ? QueryResult::STATUS_PAID
            : QueryResult::STATUS_PENDING;
    }
}
