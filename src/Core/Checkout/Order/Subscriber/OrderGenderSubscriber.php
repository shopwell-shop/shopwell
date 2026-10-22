<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Order\OrderEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderGenderSubscriber implements EventSubscriberInterface
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
            OrderEvents::ORDER_ADDRESS_WRITTEN_EVENT => 'setDefaultOrderAddressGender',
            OrderEvents::ORDER_CUSTOMER_WRITTEN_EVENT => 'setDefaultOrderCustomerGender',
        ];
    }

    public function setDefaultOrderAddressGender(EntityWrittenEvent $event): void
    {
        $this->setDefaultGender($event, 'order_address');
    }

    public function setDefaultOrderCustomerGender(EntityWrittenEvent $event): void
    {
        $this->setDefaultGender($event, 'order_customer');
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
