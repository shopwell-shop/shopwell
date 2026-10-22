<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation\DictionaryEntryTranslationCollection;
use Shopwell\Core\System\Dictionary\DictionaryTypeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryEntity extends Entity
{
    use EntityIdTrait;

    protected string $dictionaryTypeId;

    protected string $technicalName;

    protected int $position;

    protected bool $active;

    protected ?string $label = null;

    protected ?DictionaryEntryTranslationCollection $translations = null;

    protected ?DictionaryTypeEntity $dictionaryType = null;

    protected ?CustomerCollection $customers = null;

    protected ?CustomerAddressCollection $customerAddresses = null;

    protected ?OrderCustomerCollection $orderCustomers = null;

    protected ?OrderAddressCollection $orderAddresses = null;

    protected ?NewsletterRecipientCollection $newsletterRecipients = null;

    public function getDictionaryTypeId(): string
    {
        return $this->dictionaryTypeId;
    }

    public function setDictionaryTypeId(string $dictionaryTypeId): void
    {
        $this->dictionaryTypeId = $dictionaryTypeId;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTranslations(): ?DictionaryEntryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(DictionaryEntryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDictionaryType(): ?DictionaryTypeEntity
    {
        return $this->dictionaryType;
    }

    public function setDictionaryType(DictionaryTypeEntity $dictionaryType): void
    {
        $this->dictionaryType = $dictionaryType;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getCustomerAddresses(): ?CustomerAddressCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(CustomerAddressCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getOrderCustomers(): ?OrderCustomerCollection
    {
        return $this->orderCustomers;
    }

    public function setOrderCustomers(OrderCustomerCollection $orderCustomers): void
    {
        $this->orderCustomers = $orderCustomers;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getNewsletterRecipients(): ?NewsletterRecipientCollection
    {
        return $this->newsletterRecipients;
    }

    public function setNewsletterRecipients(NewsletterRecipientCollection $newsletterRecipients): void
    {
        $this->newsletterRecipients = $newsletterRecipients;
    }
}
