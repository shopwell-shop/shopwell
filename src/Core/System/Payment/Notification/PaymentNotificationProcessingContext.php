<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Shopwell\Core\Framework\Log\Package;

/**
 * The input and journal state of one notification processing run.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class PaymentNotificationProcessingContext
{
    public string $result = PaymentNotificationEntity::RESULT_ERROR;

    public bool $signatureValid = false;

    public ?string $orderId = null;

    public ?string $attemptId = null;

    public ?string $message = null;

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        public readonly string $providerTechnicalName,
        public readonly string $rawBody,
        public readonly array $headers,
        public readonly ?string $remoteIp,
        public readonly ?string $paymentNoHint = null,
    ) {
    }
}
