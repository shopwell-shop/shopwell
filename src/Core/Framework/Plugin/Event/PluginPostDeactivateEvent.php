<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Context\DeactivateContext;
use Shopwell\Core\Framework\Plugin\PluginEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginPostDeactivateEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly DeactivateContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): DeactivateContext
    {
        return $this->context;
    }
}
