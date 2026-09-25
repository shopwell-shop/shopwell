<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Context\UpdateContext;
use Shopwell\Core\Framework\Plugin\PluginEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginPostUpdateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly UpdateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): UpdateContext
    {
        return $this->context;
    }
}
