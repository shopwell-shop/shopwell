<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\DataAbstractionLayer;

use Shopwell\Core\Checkout\Payment\PaymentEvents;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentHandlerIdentifierSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'formatHandlerIdentifier',
            'payment_method.partial_loaded' => 'formatHandlerIdentifier',
        ];
    }

    /**
     * @param EntityLoadedEvent<PaymentMethodEntity|PartialEntity> $event
     */
    public function formatHandlerIdentifier(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            $entity->assign([
                'shortName' => $this->getShortName($entity),
                'formattedHandlerIdentifier' => $this->getHandlerIdentifier($entity),
            ]);
        }
    }

    private function getHandlerIdentifier(Entity $entity): string
    {
        $explodedHandlerIdentifier = explode('\\', (string) $entity->get('handlerIdentifier'));

        if (\count($explodedHandlerIdentifier) < 2) {
            return $entity->get('handlerIdentifier');
        }

        $firstHandlerIdentifier = array_shift($explodedHandlerIdentifier);
        $lastHandlerIdentifier = array_pop($explodedHandlerIdentifier);
        if ($firstHandlerIdentifier === null || $lastHandlerIdentifier === null) {
            return '';
        }

        return 'handler_'
            . mb_strtolower($firstHandlerIdentifier)
            . '_'
            . mb_strtolower($lastHandlerIdentifier);
    }

    private function getShortName(Entity $entity): string
    {
        $explodedHandlerIdentifier = explode('\\', (string) $entity->get('handlerIdentifier'));

        $last = $explodedHandlerIdentifier[\count($explodedHandlerIdentifier) - 1];

        return (new CamelCaseToSnakeCaseNameConverter())->normalize($last);
    }
}
