<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Events;

use Shopwell\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.8.0 - Will be removed - use MailFlowDataCriteriaEvent instead
 */
#[Package('after-sales')]
class BeforeLoadStorableFlowDataEvent extends Event implements ShopwellEvent, GenericEvent
{
    public function __construct(
        private readonly string $entityName,
        private readonly Criteria $criteria,
        private readonly Context $context,
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', MailFlowDataCriteriaEvent::class)
        );
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return 'flow.storer.' . $this->entityName . '.criteria.event';
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->criteria;
    }

    public function getEntityName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->entityName;
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->context;
    }
}
