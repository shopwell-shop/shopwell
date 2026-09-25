<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class PermissionsGrantedEvent implements ShopwellEvent
{
    public function __construct(
        public PermissionsConsent $permissionsConsent,
        public Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
