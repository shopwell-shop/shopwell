<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<CustomerEntity, CustomerCollection>
 */
#[Package('after-sales')]
class CustomerProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'salutation',
            'defaultBillingAddress.country',
            'defaultBillingAddress.countryState',
            'defaultBillingAddress.salutation',
            'defaultShippingAddress.country',
            'defaultShippingAddress.countryState',
            'defaultShippingAddress.salutation',
        ]);

        return $criteria;
    }
}
