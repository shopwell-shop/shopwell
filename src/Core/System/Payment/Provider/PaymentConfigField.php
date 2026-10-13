<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;

/**
 * One input of the configuration of a payment interface or of one of its channels.
 *
 * The administration renders the settings form of an interface from these declarations instead of
 * knowing the fields of every interface itself, so an interface of an extension is configurable without
 * touching the administration at all. The fields are declared in the `payment-provider.xml` of the
 * interface and are turned into these objects by the {@see PaymentProviderSchemaReader}.
 *
 * A field marked as `secret` is never handed back to the administration: the form always starts empty
 * and only reports whether a value is stored. Every other field is echoed back, so an operator can see
 * which merchant account an interface is configured with.
 */
#[Package('framework')]
final readonly class PaymentConfigField
{
    /**
     * A single line input for a value that is not secret, e.g. a merchant id.
     */
    final public const TYPE_TEXT = 'text';

    /**
     * A single line input whose value is never echoed back, e.g. an api key.
     */
    final public const TYPE_PASSWORD = 'password';

    /**
     * A multi line input for a value that does not fit into one line, e.g. a private key.
     */
    final public const TYPE_TEXTAREA = 'textarea';

    /**
     * A fixed set of values, the options are the selectable ones.
     */
    final public const TYPE_SELECT = 'select';

    /**
     * A switch, e.g. whether the sandbox of an interface is used.
     */
    final public const TYPE_BOOL = 'bool';

    /**
     * A numeric input, e.g. a timeout or a limit.
     */
    final public const TYPE_NUMBER = 'number';

    /**
     * @param string $name the key of the value inside the configuration payload
     * @param string|null $label the snippet key of the label, the administration falls back to the name
     * @param array<string, string> $options the selectable values of a select field, given as value => label
     */
    public function __construct(
        public string $name,
        public ?string $label = null,
        public string $type = self::TYPE_TEXT,
        public bool $required = false,
        public bool $secret = false,
        public array $options = [],
        public ?string $default = null,
        public ?string $helpText = null,
    ) {
    }

    public function isSelect(): bool
    {
        return $this->type === self::TYPE_SELECT;
    }

    /**
     * The declaration as the administration consumes it. The label is deliberately handed over as a
     * snippet key: the administration translates it, or falls back to the name of the field.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'secret' => $this->secret,
            'options' => $this->options,
            'default' => $this->default,
            'helpText' => $this->helpText,
        ];
    }
}
