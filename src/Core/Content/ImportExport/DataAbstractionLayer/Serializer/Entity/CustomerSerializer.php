<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Contracts\Service\ResetInterface;

#[Package('fundamentals@after-sales')]
class CustomerSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param array<string, string|null> $cacheCustomerGroups
     * @param array<string, string|null> $cacheSalesChannels
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $salesChannelRepository,
        private array $cacheCustomerGroups = [],
        private array $cacheSalesChannels = [],
    ) {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $context = Context::createDefaultContext();

        if (!isset($deserialized['groupId']) && isset($entity['group'])) {
            $name = $entity['group']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['group']['id'] ?? $this->getCustomerGroupId($name, $context);

            if ($id) {
                $deserialized['group']['id'] = $id;
            }
        }

        if (!isset($deserialized['salesChannelId']) && isset($entity['salesChannel'])) {
            $name = $entity['salesChannel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['salesChannel']['id'] ?? $this->getSalesChannelId($name, $context);

            if ($id) {
                $deserialized['salesChannel']['id'] = $id;
            }
        }

        if (!isset($deserialized['boundSalesChannelId']) && isset($entity['boundSalesChannel'])) {
            $name = $entity['boundSalesChannel']['translations']['DEFAULT']['name'] ?? null;
            $id = $entity['boundSalesChannel']['id'] ?? $this->getSalesChannelId($name, $context);

            if ($id) {
                $deserialized['boundSalesChannel']['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === CustomerDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheCustomerGroups = [];
        $this->cacheSalesChannels = [];
    }

    private function getCustomerGroupId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheCustomerGroups)) {
            return $this->cacheCustomerGroups[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $this->cacheCustomerGroups[$name] = $this->customerGroupRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheCustomerGroups[$name];
    }

    private function getSalesChannelId(?string $name, Context $context): ?string
    {
        if (!$name) {
            return null;
        }

        if (\array_key_exists($name, $this->cacheSalesChannels)) {
            return $this->cacheSalesChannels[$name];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        $this->cacheSalesChannels[$name] = $this->salesChannelRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheSalesChannels[$name];
    }
}
