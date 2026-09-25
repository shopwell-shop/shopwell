<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class ScalarBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    private string $string = 'string';

    private true $bool = true;

    private int $int = 3;

    private float $float = 1.3;

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('string', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('bool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
            ->add('int', new ScalarValueType(ScalarValueType::TYPE_INT))
            ->add('float', new ScalarValueType(ScalarValueType::TYPE_FLOAT));
    }

    public function getEncodeValues(string $shopwellVersion): array
    {
        return [
            'string' => $this->getString(),
            'bool' => $this->isBool(),
            'int' => $this->getInt(),
            'float' => $this->getFloat(),
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

    public function getString(): string
    {
        return $this->string;
    }

    public function isBool(): bool
    {
        return $this->bool;
    }

    public function getInt(): int
    {
        return $this->int;
    }

    public function getFloat(): float
    {
        return $this->float;
    }
}
