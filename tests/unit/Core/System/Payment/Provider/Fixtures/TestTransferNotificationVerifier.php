<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * A payout notification verifier that verifies nothing.
 *
 * @internal
 */
#[Package('framework')]
final class TestTransferNotificationVerifier implements PaymentTransferNotificationVerifierInterface
{
    public function provider(): string
    {
        return 'testpay';
    }

    public function verify(array $credentials, string $rawBody, array $headers): bool
    {
        return false;
    }

    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string
    {
        return null;
    }

    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('ok');
    }
}
