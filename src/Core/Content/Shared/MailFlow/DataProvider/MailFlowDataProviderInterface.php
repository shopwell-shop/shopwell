<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\DataProvider;

use Shopwell\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @template TEntity of Entity
 */
#[Package('after-sales')]
interface MailFlowDataProviderInterface
{
    public function getEntityName(): string;

    /**
     * Implementations should dispatch {@see MailFlowDataCriteriaEvent} when building the criteria
     * so provider-specific criteria can still be extended by listeners.
     */
    public function getCriteria(string $entityId, Context $context): Criteria;

    /**
     * @return TEntity|null
     */
    public function getData(string $entityId, Context $context): ?Entity;
}
