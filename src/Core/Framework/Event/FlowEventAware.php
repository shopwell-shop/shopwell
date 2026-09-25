<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface FlowEventAware extends ShopwellEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
