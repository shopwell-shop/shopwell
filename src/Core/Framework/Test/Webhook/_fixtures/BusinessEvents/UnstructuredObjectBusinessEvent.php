<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ObjectType;
use Shopwell\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class UnstructuredObjectBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    /**
     * @var array{string: 'test', bool: true}
     */
    private array $nested = [
        'string' => 'test',
        'bool' => true,
    ];

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('nested', new ObjectType());
    }

    public function getEncodeValues(string $shopwellVersion): array
    {
        return [
            'nested' => $this->getNested(),
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

    /**
     * @return array{string: 'test', bool: true}
     */
    public function getNested(): array
    {
        return $this->nested;
    }
}
