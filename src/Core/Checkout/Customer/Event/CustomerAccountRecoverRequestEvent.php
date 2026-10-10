<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Event;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\CustomerAware;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\SalesChannelContextAware;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\NotHookable;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
#[NotHookable]
class CustomerAccountRecoverRequestEvent extends Event implements SalesChannelContextAware, ShopwellSalesChannelEvent, CustomerAware, MailAware, CustomerRecoveryAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'customer.recovery.request';

    private readonly string $shopName;

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerRecoveryEntity $customerRecovery,
        private readonly string $resetUrl,
    ) {
        $this->shopName = $salesChannelContext->getSalesChannel()->getTranslation('name');
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::RESET_URL => $this->resetUrl,
            FlowMailVariables::SHOP_NAME => $this->shopName,
        ];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomerRecovery(): CustomerRecoveryEntity
    {
        return $this->customerRecovery;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerRecoveryAware::CUSTOMER_RECOVERY, new EntityType(CustomerRecoveryDefinition::class))
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(FlowMailVariables::RESET_URL, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(FlowMailVariables::SHOP_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct) {
            $customer = $this->customerRecovery->getCustomer();
            \assert($customer !== null);

            $this->mailRecipientStruct = new MailRecipientStruct([
                $customer->getEmail() => $customer->getName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getShopName(): string
    {
        return $this->shopName;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customerRecovery->getCustomer();
    }

    public function getCustomerId(): string
    {
        return $this->getCustomerRecovery()->getCustomerId();
    }

    public function getCustomerRecoveryId(): string
    {
        return $this->customerRecovery->getId();
    }
}
