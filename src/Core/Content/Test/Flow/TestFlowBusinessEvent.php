<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Test\Flow;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('after-sales')]
class TestFlowBusinessEvent extends Event implements FlowEventAware
{
    public const EVENT_NAME = 'test.flow_event';

    protected string $name = self::EVENT_NAME;

    public function __construct(protected Context $context)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
