<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;

/**
 * Form fields available when validating a definition; assignee existence is checked at runtime.
 *
 * @internal
 */
#[Package('framework')]
final class ValidationContext
{
    /**
     * @param list<string> $formKeys
     * @param array<string, string> $formTypes
     */
    public function __construct(
        public readonly array $formKeys = [],
        public readonly array $formTypes = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Build validation context from the definition's form schema.
     *
     * @param list<array<string, mixed>>|null $formSchema
     */
    public static function fromFormSchema(?array $formSchema): self
    {
        $keys = [];
        $types = [];

        foreach ($formSchema ?? [] as $field) {
            if (!\is_array($field) || !isset($field['key']) || !\is_string($field['key']) || $field['key'] === '') {
                continue;
            }

            $keys[] = $field['key'];
            $types[$field['key']] = \is_string($field['type'] ?? null) ? $field['type'] : 'string';
        }

        return new self($keys, $types);
    }

    public function hasFormKey(string $key): bool
    {
        return \in_array($key, $this->formKeys, true);
    }
}
