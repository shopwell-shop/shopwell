<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\System\Tax\TaxDefinition;
use Shopwell\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class HiddenEntityBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxEntity $tax)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('tax', new EntityType(TaxDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);
    }

    public function getEncodeValues(string $shopwellVersion): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getTax(): TaxEntity
    {
        return $this->tax;
    }
}
