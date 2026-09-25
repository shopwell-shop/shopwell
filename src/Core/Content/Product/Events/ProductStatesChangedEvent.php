<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
 */
#[Package('inventory')]
class ProductStatesChangedEvent extends Event implements ShopwellEvent
{
    /**
     * @param UpdatedStates[] $updatedStates
     */
    public function __construct(
        protected array $updatedStates,
        protected Context $context
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     *
     * @return UpdatedStates[]
     */
    public function getUpdatedStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getUpdatedStates', 'v6.8.0.0')
        );

        return $this->updatedStates;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getContext', 'v6.8.0.0')
        );

        return $this->context;
    }
}
