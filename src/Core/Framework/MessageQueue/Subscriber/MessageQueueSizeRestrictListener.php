<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Subscriber;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\MessageQueueException;
use Shopwell\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

#[Package('framework')]
readonly class MessageQueueSizeRestrictListener
{
    /**
     * @deprecated tag:v6.8.0 - Use the configuration option `shopwell.messenger.message_max_kib_size` instead.
     * @see https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/quotas-messages.html
     * Maximum message size is 262144 (1024 * 256) bytes
     */
    public const MESSAGE_SIZE_LIMIT = 1024 * 256;

    /**
     * @internal
     */
    public function __construct(
        private MessageSizeCalculator $calculator,
        private bool $enforceMessageSize,
        private int $messageMaxKiBSize,
    ) {
    }

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        if (!$this->enforceMessageSize || $this->messageMaxKiBSize <= 0) {
            return;
        }

        /**
         * If the message is sent to the SyncTransport, it means that the message is not sent to any other transport so it can be ignored.
         */
        foreach ($event->getSenders() as $sender) {
            if ($sender instanceof SyncTransport) {
                return;
            }
        }

        $messageLengthInBytes = $this->calculator->size($event->getEnvelope());
        if ($messageLengthInBytes > $this->messageMaxKiBSize * 1024) {
            $messageName = $event->getEnvelope()->getMessage()::class;

            throw MessageQueueException::maxQueueMessageSizeExceeded($messageName, $messageLengthInBytes / 1024, $this->messageMaxKiBSize);
        }
    }
}
