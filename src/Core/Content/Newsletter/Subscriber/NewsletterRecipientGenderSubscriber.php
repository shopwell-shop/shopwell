<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Subscriber;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Newsletter\NewsletterEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class NewsletterRecipientGenderSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NewsletterEvents::NEWSLETTER_RECIPIENT_WRITTEN_EVENT => 'setDefaultGender',
        ];
    }

    public function setDefaultGender(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();
        foreach ($payloads as $payload) {
            if (\array_key_exists('gender', $payload) && $payload['gender']) {
                continue;
            }

            if (!isset($payload['id'])) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE `newsletter_recipient` SET `gender` = :notSpecified WHERE `id` = :id AND `gender` IS NULL',
                ['id' => Uuid::fromHexToBytes($payload['id']), 'notSpecified' => DictionaryEntryDefinition::NOT_SPECIFIED]
            );
        }
    }
}
