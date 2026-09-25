<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopwell\Core\Content\Flow\Dispatching\DelayableAction;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\CustomerGroupAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class SetCustomerGroupCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $customerGroupRepository
    ) {
    }

    public static function getName(): string
    {
        return 'action.set.customer.group.custom.field';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [CustomerGroupAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(CustomerGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(CustomerGroupAware::CUSTOMER_GROUP_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $customerGroupId): void
    {
        $customerGroup = $this->customerGroupRepository->search(new Criteria([$customerGroupId]), $context)->getEntities()->first();

        $customFields = $this->getCustomFieldForUpdating($customerGroup?->getCustomFields(), $config);
        if ($customFields === null) {
            return;
        }

        $customFields = $customFields === [] ? null : $customFields;

        $this->customerGroupRepository->update([
            [
                'id' => $customerGroupId,
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
