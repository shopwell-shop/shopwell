<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Outbox;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class OutboxEntry
{
    public function __construct(
        public string $webhookEventId,
        public int $sequence,
        public int $executionCount,
        public string $deliveryStatus,
        public ?string $serializedWebhookMessage = null,
    ) {
    }
}
