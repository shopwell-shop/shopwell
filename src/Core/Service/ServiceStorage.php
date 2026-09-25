<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\DTO\Service;

/**
 * @internal
 */
#[Package('framework')]
class ServiceStorage
{
    /**
     * @param EntityRepository<AppCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function findByName(string $name, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    public function findByNameAndIntegrationId(string $name, string $integrationId, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    public function findByIntegrationId(string $integrationId, Context $context): ?Service
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->setLimit(1);

        return $this->wrap($this->repository->search($criteria, $context)->getEntities()->first());
    }

    /**
     * @return list<Service>
     */
    public function findAll(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', true));
        $criteria->addAssociation('aclRole');

        return array_values($this->repository->search($criteria, $context)->getEntities()->map(
            static fn (AppEntity $app): Service => Service::fromApp($app)
        ));
    }

    private function wrap(?AppEntity $app): ?Service
    {
        if ($app === null) {
            return null;
        }

        return Service::fromApp($app);
    }
}
