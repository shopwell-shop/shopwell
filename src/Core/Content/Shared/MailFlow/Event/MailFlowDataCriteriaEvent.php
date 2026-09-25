<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Shared\MailFlow\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailFlowDataCriteriaEvent extends Event implements ShopwellEvent, GenericEvent
{
    public function __construct(
        public readonly string $entityName,
        public readonly Criteria $criteria,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return 'mail-flow.data.' . $this->entityName . '.criteria.event';
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
