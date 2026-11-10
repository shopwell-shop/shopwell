<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Provider\Config;

use Shopwell\Core\Framework\Log\Package;

/**
 * A credential or connection field declared by a provider and rendered by Administration.
 */
#[Package('framework')]
final readonly class SmsConfigField
{
    /**
     * @param 'string'|'secret'|'json'|'bool'|'int'|'select' $type
     * @param list<array{value: string|int, labelSnippet: string}>|null $options
     */
    public function __construct(
        public string $name,
        public string $labelSnippet,
        public string $type = 'string',
        public bool $required = false,
        public string|int|bool|null $default = null,
        public ?string $helpSnippet = null,
        public ?array $options = null,
    ) {
    }

    /**
     * @return array{name: string, type: 'string'|'secret'|'json'|'bool'|'int'|'select', required: bool, labelSnippet: string, default?: string|int|bool, helpSnippet?: string, options?: list<array{value: string|int, labelSnippet: string}>}
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'labelSnippet' => $this->labelSnippet,
            'default' => $this->default,
            'helpSnippet' => $this->helpSnippet,
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
