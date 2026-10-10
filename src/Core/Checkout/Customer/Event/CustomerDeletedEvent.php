<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Event;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\CustomerAware;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriberTest
 */
#[Package('checkout')]
class CustomerDeletedEvent extends Event implements ShopwellSalesChannelEvent, CustomerAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.customer.deleted';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    /**
     * @param array<string, mixed> $serializedCustomer
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly array $serializedCustomer = []
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->customer->getEmail() => $this->customer->getName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    public function getValues(): array
    {
        return [
            'customer' => $this->serializedCustomer,
        ];
    }
}
