<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentCashierException extends PaymentException
{
    public static function unavailable(string $id): self
    {
        return new self(Response::HTTP_NOT_FOUND, 'PAYMENT__CASHIER_UNAVAILABLE', 'The cashier or session "{{ id }}" is unavailable.', ['id' => $id]);
    }

    public static function expired(string $id): self
    {
        return new self(Response::HTTP_GONE, 'PAYMENT__CASHIER_EXPIRED', 'The cashier session "{{ id }}" has expired.', ['id' => $id]);
    }

    public static function conflict(string $id): self
    {
        return new self(Response::HTTP_CONFLICT, 'PAYMENT__CASHIER_CONFLICT', 'The cashier session "{{ id }}" was already created or paid with different parameters.', ['id' => $id]);
    }
}
