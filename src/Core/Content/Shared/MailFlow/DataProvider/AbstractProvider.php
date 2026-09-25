<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @template TEntity of Entity
 * @template TEntityCollection of EntityCollection<TEntity>
 *
 * @implements MailFlowDataProviderInterface<TEntity>
 */
#[Package('after-sales')]
abstract class AbstractProvider implements MailFlowDataProviderInterface
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly ContainerInterface $container,
    ) {
    }

    abstract public function getEntityName(): string;

    public function getCriteria(string $entityId, Context $context): Criteria
    {
        $criteria = $this->constructCriteria($entityId);

        $event = new MailFlowDataCriteriaEvent(
            $this->getEntityName(),
            $criteria,
            $context,
        );

        $this->eventDispatcher->dispatch($event, $event->getName());

        return $criteria;
    }

    /**
     * @return TEntity|null
     */
    public function getData(string $entityId, Context $context): ?Entity
    {
        $criteria = $this->getCriteria($entityId, $context);

        /** @var TEntity|null $entity */
        $entity = $this->getRepository()->search($criteria, $context)->getEntities()->get($entityId);

        return $entity;
    }

    /**
     * @return EntityRepository<TEntityCollection>
     */
    protected function getRepository(): EntityRepository
    {
        /** @var EntityRepository<TEntityCollection> $repository */
        $repository = $this->container->get($this->getEntityName() . '.repository');

        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    abstract protected function constructCriteria(string $entityId): Criteria;
}
