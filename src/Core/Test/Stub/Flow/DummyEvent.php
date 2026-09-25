<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\Flow;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class DummyEvent extends Event implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'dummy.event';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}
