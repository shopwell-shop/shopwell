<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Subscriber;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopwell\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelTypeValidator implements EventSubscriberInterface
{
    private const PROTECTED_SALES_CHANNEL_TYPE_IDS = [
        Defaults::SALES_CHANNEL_TYPE_API => true,
        Defaults::SALES_CHANNEL_TYPE_STOREFRONT => true,
        Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON => true,
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE => true,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidateEvent',
        ];
    }

    public function preWriteValidateEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getDeletedPrimaryKeys(SalesChannelTypeDefinition::ENTITY_NAME) as $primaryKey) {
            $id = Uuid::fromBytesToHex($primaryKey['id']);

            if (\array_key_exists($id, self::PROTECTED_SALES_CHANNEL_TYPE_IDS)) {
                $event->getExceptions()->add(new DefaultSalesChannelTypeCannotBeDeleted($id));
            }
        }
    }
}
