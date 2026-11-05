<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A proved terminal rejection, as opposed to a call with an unknown outcome.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentRefundRejectedException extends PaymentException
{
    public static function rejected(string $channel, string $message): self
    {
        return new self(
            Response::HTTP_BAD_GATEWAY,
            self::GATEWAY_ERROR,
            'The payment channel "{{ channel }}" returned an error: {{ message }}',
            ['channel' => $channel, 'message' => $message]
        );
    }
}
