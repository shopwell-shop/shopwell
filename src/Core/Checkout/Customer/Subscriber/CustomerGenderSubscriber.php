<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerGenderSubscriber implements EventSubscriberInterface
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
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'setDefaultCustomerGender',
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'setDefaultCustomerAddressGender',
        ];
    }

    public function setDefaultCustomerGender(EntityWrittenEvent $event): void
    {
        $this->setDefaultGender($event, 'customer');
    }

    public function setDefaultCustomerAddressGender(EntityWrittenEvent $event): void
    {
        $this->setDefaultGender($event, 'customer_address');
    }

    private function setDefaultGender(EntityWrittenEvent $event, string $table): void
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
                \sprintf(
                    'UPDATE `%s` SET `gender` = :notSpecified WHERE `id` = :id AND `gender` IS NULL',
                    $table
                ),
                ['id' => Uuid::fromHexToBytes($payload['id']), 'notSpecified' => DictionaryEntryDefinition::NOT_SPECIFIED]
            );
        }
    }
}
