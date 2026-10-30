<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopwell\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;

#[Package('discovery')]
class CustomerGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    final public const PRICE_BASIS_NET = 'net';

    final public const PRICE_BASIS_GROSS = 'gross';

    protected ?string $name = null;

    protected bool $displayGross;

    #[PropertyTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected ?string $priceBasis = null;

    protected ?CustomerGroupTranslationCollection $translations = null;

    protected ?CustomerCollection $customers = null;

    protected ?SalesChannelCollection $salesChannels = null;

    protected bool $registrationActive;

    protected ?string $registrationTitle = null;

    protected ?string $registrationIntroduction = null;

    protected ?bool $registrationOnlyCompanyRegistration = null;

    protected ?string $registrationSeoMetaDescription = null;

    protected ?SalesChannelCollection $registrationSalesChannels = null;

    protected ?LoyaltyTierCollection $loyaltyTiers = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayGross(): bool
    {
        return $this->displayGross;
    }

    public function setDisplayGross(bool $displayGross): void
    {
        $this->displayGross = $displayGross;
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getPriceBasis(): ?string
    {
        return $this->priceBasis;
    }

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'priceBasis', newType: 'string')]
    public function setPriceBasis(?string $priceBasis): void
    {
        if ($priceBasis === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing null to CustomerGroupEntity::setPriceBasis() will not be possible from v6.8.0.0 on, pass "net" or "gross" instead.'
            );
        }

        $this->priceBasis = $priceBasis;
    }

    public function getTranslations(): ?CustomerGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomerGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getRegistrationActive(): bool
    {
        return $this->registrationActive;
    }

    public function setRegistrationActive(bool $registrationActive): void
    {
        $this->registrationActive = $registrationActive;
    }

    public function getRegistrationTitle(): ?string
    {
        return $this->registrationTitle;
    }

    public function setRegistrationTitle(string $registrationTitle): void
    {
        $this->registrationTitle = $registrationTitle;
    }

    public function getRegistrationIntroduction(): ?string
    {
        return $this->registrationIntroduction;
    }

    public function setRegistrationIntroduction(string $registrationIntroduction): void
    {
        $this->registrationIntroduction = $registrationIntroduction;
    }

    public function getRegistrationOnlyCompanyRegistration(): ?bool
    {
        return $this->registrationOnlyCompanyRegistration;
    }

    public function setRegistrationOnlyCompanyRegistration(bool $registrationOnlyCompanyRegistration): void
    {
        $this->registrationOnlyCompanyRegistration = $registrationOnlyCompanyRegistration;
    }

    public function getRegistrationSeoMetaDescription(): ?string
    {
        return $this->registrationSeoMetaDescription;
    }

    public function setRegistrationSeoMetaDescription(string $registrationSeoMetaDescription): void
    {
        $this->registrationSeoMetaDescription = $registrationSeoMetaDescription;
    }

    public function getRegistrationSalesChannels(): ?SalesChannelCollection
    {
        return $this->registrationSalesChannels;
    }

    public function setRegistrationSalesChannels(SalesChannelCollection $registrationSalesChannels): void
    {
        $this->registrationSalesChannels = $registrationSalesChannels;
    }

    public function getLoyaltyTiers(): ?LoyaltyTierCollection
    {
        return $this->loyaltyTiers;
    }

    public function setLoyaltyTiers(LoyaltyTierCollection $loyaltyTiers): void
    {
        $this->loyaltyTiers = $loyaltyTiers;
    }
}
