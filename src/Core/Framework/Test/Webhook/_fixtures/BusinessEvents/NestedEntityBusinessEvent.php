<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ObjectType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\System\Tax\TaxDefinition;
use Shopwell\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class NestedEntityBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxEntity $tax)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('object', (new ObjectType())
                ->add('tax', new EntityType(TaxDefinition::class)));
    }

    public function getEncodeValues(string $shopwellVersion): array
    {
        return [
            'object' => [
                'tax' => [
                    'id' => $this->tax->getId(),
                    '_uniqueIdentifier' => $this->tax->getId(),
                    'versionId' => null,
                    'name' => $this->tax->getName(),
                    'taxRate' => $this->tax->getTaxRate(),
                    'position' => $this->tax->getPosition(),
                    'customFields' => null,
                    'translated' => [],
                    'createdAt' => $this->tax->getCreatedAt() ? $this->tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED) : null,
                    'updatedAt' => null,
                    'apiAlias' => 'tax',
                ],
            ],
        ];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getObject(): EntityBusinessEvent
    {
        return new EntityBusinessEvent($this->tax);
    }
}
