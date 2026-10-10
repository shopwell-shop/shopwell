<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Event;

use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopwell\Core\Framework\Event\CustomerAware;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Event\SalesChannelAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Flow\GenerateDocumentActionTest
 */
#[Package('checkout')]
class OrderStateMachineStateChangeEvent extends Event implements SalesChannelAware, OrderAware, MailAware, CustomerAware, A11yRenderedDocumentAware, FlowEventAware
{
    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly string $name,
        private readonly OrderEntity $order,
        private readonly Context $context
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $orderCustomer = $this->order->getOrderCustomer();
            if (!$orderCustomer) {
                throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
            }

            $this->mailRecipientStruct = new MailRecipientStruct([
                $orderCustomer->getEmail() => $orderCustomer->getName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getCustomerId(): string
    {
        $orderCustomer = $this->order->getOrderCustomer();

        if (!$orderCustomer?->getCustomerId()) {
            throw OrderException::orderCustomerDeleted($this->order->getId());
        }

        return $orderCustomer->getCustomerId();
    }

    /**
     * @return array<string>
     */
    public function getA11yDocumentIds(): array
    {
        $extension = $this->context->getExtension(SendMailAction::MAIL_CONFIG_EXTENSION);
        if (!$extension instanceof MailSendSubscriberConfig) {
            return [];
        }

        return array_filter($extension->getDocumentIds());
    }
}
