<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event\EventData;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopwell\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
class EventDataCollection
{
    public const HIDDEN_FROM_WEBHOOK = 'hiddenFromWebhook';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $data = [];

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'options', parameterType: 'array', defaultValue: [], description: 'Extra per-value options merged into the declared type, e.g. EventDataCollection::HIDDEN_FROM_WEBHOOK to keep a value out of webhook payloads.')]
    public function add(string $name, EventDataType $type/* , array $options = [] */): self
    {
        /** @deprecated tag:v6.8.0 - Remove next line as $options will become part of the method signature */
        /** @var array<string, mixed> $options */
        $options = \func_get_args()[2] ?? [];

        $this->data[$name] = [...$type->toArray(), ...$options];

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
