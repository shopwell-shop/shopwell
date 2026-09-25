<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Outbox;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * Data for inserting a new outbox entry (webhook_event_log + webhook_delivery).
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class OutboxInsert
{
    public function __construct(
        public string $webhookEventId,
        public string $webhookId,
        public string $partitionKey,
        public string $serializedMessage,
    ) {
    }

    public static function fromMessage(WebhookEventMessage $message): self
    {
        return new self(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        );
    }
}
