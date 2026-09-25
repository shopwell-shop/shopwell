<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SalesChannelCustomerAddressDefinition extends CustomerAddressDefinition implements SalesChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return SalesChannelCustomerAddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelCustomerAddressCollection::class;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()?->getId()));
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new BoolField('is_default_billing_address', 'isDefaultBillingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );
        $fields->add(
            (new BoolField('is_default_shipping_address', 'isDefaultShippingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );

        return $fields;
    }
}
