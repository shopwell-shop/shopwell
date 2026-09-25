<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\DataAbstractionLayer;

use Shopwell\Core\Checkout\Payment\PaymentEvents;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentDistinguishableNameSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addDistinguishablePaymentName',
        ];
    }

    /**
     * @param EntityLoadedEvent<PaymentMethodEntity> $event
     */
    public function addDistinguishablePaymentName(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $payment) {
            if ($payment->getTranslation('distinguishableName') === null) {
                $payment->addTranslated('distinguishableName', $payment->getTranslation('name'));
            }
            if ($payment->getDistinguishableName() === null) {
                $payment->setDistinguishableName($payment->getName());
            }
        }
    }
}
