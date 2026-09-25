<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\CustomerGroupRegistration;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPage;

#[Package('checkout')]
class CustomerGroupRegistrationPage extends AccountLoginPage
{
    protected CustomerGroupEntity $customerGroup;

    public function setGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getGroup(): CustomerGroupEntity
    {
        return $this->customerGroup;
    }
}
