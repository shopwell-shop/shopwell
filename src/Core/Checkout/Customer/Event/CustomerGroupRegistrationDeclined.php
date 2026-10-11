<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Event;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\CustomerAware;
use Shopwell\Core\Framework\Event\CustomerGroupAware;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\SalesChannelAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CustomerGroupRegistrationDeclined extends Event implements SalesChannelAware, CustomerAware, MailAware, CustomerGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'customer.group.registration.declined';

    /**
     * @internal
     */
    public function __construct(
        private readonly CustomerEntity $customer,
        private readonly CustomerGroupEntity $customerGroup,
        private readonly Context $context,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(CustomerGroupAware::CUSTOMER_GROUP, new EntityType(CustomerGroupDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct(
            [
                $this->customer->getEmail() => $this->customer->getName(),
            ]
        );
    }

    public function getSalesChannelId(): string
    {
        return $this->customer->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerGroup(): CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroup->getId();
    }
}
