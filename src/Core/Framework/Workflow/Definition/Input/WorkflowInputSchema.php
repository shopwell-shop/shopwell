<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Input;

use Brick\Math\BigDecimal;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final class WorkflowInputSchema
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher = new EventDispatcher())
    {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(string $source, ?Context $context = null): array
    {
        $event = new WorkflowFormOptionsEvent($source, $context ?? Context::createDefaultContext());
        $this->dispatcher->dispatch($event);

        if ($event->options === null) {
            throw WorkflowExecutionException::invalidGraph('Input option source is unavailable: ' . $source);
        }

        return $event->options;
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<array{value: string, label: string}>, dataSource?: string}>
     */
    public function normalize(mixed $schema): array
    {
        if (!\is_array($schema) || !array_is_list($schema) || \count($schema) > 50) {
            throw $this->invalidField('/fields', null, 'Input schemas must be lists with at most 50 fields');
        }

        $fields = [];
        $keys = [];

        foreach ($schema as $index => $raw) {
            $path = '/fields/' . $index;
            $field = $this->normalizeField($raw, $path);

            if (isset($keys[$field['key']])) {
                throw $this->invalidField($path . '/key', $field['key'], 'Input field keys must be unique');
            }

            $fields[] = $field;
            $keys[$field['key']] = true;
        }

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $schema Raw schema validated by normalize().
     * @param array<string, mixed> $data
     *
     * @return array<string, string|null>
     */
    public function normalizeData(array $schema, array $data, ?Context $context = null): array
    {
        $fields = $this->normalize($schema);

        if (array_diff(array_keys($data), array_column($fields, 'key')) !== []) {
            $unknown = array_values(array_diff(array_keys($data), array_column($fields, 'key')));
            throw $this->invalidField('/data/' . $unknown[0], $unknown[0], 'Submitted data contains an undefined input field');
        }

        $normalized = [];

        foreach ($fields as $field) {
            $value = $data[$field['key']] ?? null;

            if ($value === null || $value === '' || ($field['type'] === 'text' && \is_string($value) && trim($value) === '')) {
                if ($field['required']) {
                    throw $this->invalidField('/data/' . $field['key'], $field['key'], 'A value is required for: ' . $field['label']);
                }

                if (\array_key_exists($field['key'], $data)) {
                    $normalized[$field['key']] = null;
                }

                continue;
            }

            try {
                $normalized[$field['key']] = $this->normalizeValue($field, $value);

                if (isset($field['dataSource']) && !\in_array($normalized[$field['key']], array_column($this->options($field['dataSource'], $context), 'value'), true)) {
                    throw WorkflowExecutionException::invalidGraph('Select a valid option for: ' . $field['label']);
                }
            } catch (WorkflowExecutionException $exception) {
                throw $this->invalidField('/data/' . $field['key'], $field['key'], $exception->getParameters()['reason']);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $field
     */
    public function normalizeValue(array $field, mixed $value): string
    {
        $type = $field['type'];

        if (\in_array($type, ['amount', 'number'], true)) {
            if (\is_int($value) || (\is_float($value) && is_finite($value))) {
                $encoded = json_encode($value, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
                $value = (string) BigDecimal::of($encoded)->strippedOfTrailingZeros();
            }

            $pattern = $type === 'amount' ? '/^\d{1,12}(?:\.\d{1,2})?$/D' : '/^-?\d{1,12}(?:\.\d{1,6})?$/D';

            if (\is_string($value) && preg_match($pattern, $value) === 1) {
                $decimal = BigDecimal::of($value);

                return (string) ($type === 'amount' ? $decimal->toScale(2) : $decimal->strippedOfTrailingZeros());
            }
        } elseif (\is_string($value)) {
            if ($type === 'text' && mb_strlen($value) <= 4000) {
                return $value;
            }

            if ($type === 'select' && \in_array($value, array_column($field['options'] ?? [], 'value'), true)) {
                return $value;
            }

            if ($type === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) === 1 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return $value;
            }
        }

        throw WorkflowExecutionException::invalidGraph('Invalid value for input field: ' . $field['label']);
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, options?: list<array{value: string, label: string}>, dataSource?: string}
     */
    private function normalizeField(mixed $raw, string $path): array
    {
        $field = $raw instanceof \stdClass ? get_object_vars($raw) : $raw;

        if (!\is_array($field)) {
            throw $this->invalidField($path, null, 'Input fields must be objects');
        }

        $key = \is_string($field['key'] ?? null) ? $field['key'] : null;
        $unknown = array_diff(array_keys($field), ['key', 'label', 'type', 'required', 'options', 'dataSource']);

        if ($unknown !== []) {
            throw $this->invalidField($path . '/' . reset($unknown), $key, 'Unknown input field configuration');
        }

        if ($key === null || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key) !== 1) {
            throw $this->invalidField($path . '/key', $key, 'Invalid input field key');
        }

        if (!\is_string($field['label'] ?? null) || trim($field['label']) === '' || mb_strlen($field['label']) > 255) {
            throw $this->invalidField($path . '/label', $key, 'Input field labels must be nonblank strings with at most 255 characters');
        }

        if (!\in_array($field['type'] ?? null, ['text', 'amount', 'number', 'date', 'select'], true)) {
            throw $this->invalidField($path . '/type', $key, 'Invalid input field type');
        }

        if (\array_key_exists('required', $field) && !\is_bool($field['required'])) {
            throw $this->invalidField($path . '/required', $key, 'The required setting must be a boolean');
        }

        $normalized = [
            'key' => $key,
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'] ?? false,
        ];

        if (\array_key_exists('dataSource', $field)) {
            if ($field['type'] !== 'text' || !\is_string($field['dataSource']) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $field['dataSource']) !== 1) {
                throw $this->invalidField($path . '/dataSource', $key, 'Invalid input option source configuration');
            }

            $normalized['dataSource'] = $field['dataSource'];
        }

        if ($field['type'] === 'select') {
            $normalized['options'] = $this->normalizeOptions($field['options'] ?? null, $path . '/options', $key);
        } elseif (\array_key_exists('options', $field)) {
            throw $this->invalidField($path . '/options', $key, 'Only select fields may configure options');
        }

        return $normalized;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(mixed $options, string $path, string $key): array
    {
        if (!\is_array($options) || !array_is_list($options) || $options === [] || \count($options) > 50) {
            throw $this->invalidField($path, $key, 'Select fields require a list of 1 to 50 options');
        }

        $normalized = [];
        $values = [];

        foreach ($options as $index => $raw) {
            $option = $raw instanceof \stdClass ? get_object_vars($raw) : $raw;
            $optionPath = $path . '/' . $index;

            if (!\is_array($option) || array_diff(array_keys($option), ['value', 'label']) !== []) {
                throw $this->invalidField($optionPath, $key, 'Invalid option configuration');
            }

            if (!\is_string($option['value'] ?? null) || trim($option['value']) === '' || mb_strlen($option['value']) > 255 || \in_array($option['value'], $values, true)) {
                throw $this->invalidField($optionPath . '/value', $key, 'Option values must be unique nonblank strings with at most 255 characters');
            }

            if (!\is_string($option['label'] ?? null) || trim($option['label']) === '' || mb_strlen($option['label']) > 255) {
                throw $this->invalidField($optionPath . '/label', $key, 'Option labels must be nonblank strings with at most 255 characters');
            }

            $values[] = $option['value'];
            $normalized[] = ['value' => $option['value'], 'label' => $option['label']];
        }

        return $normalized;
    }

    private function invalidField(string $path, ?string $key, string $reason): WorkflowExecutionException
    {
        return WorkflowExecutionException::invalidGraph($path . ($key === null ? '' : ' [' . $key . ']') . ': ' . $reason, $path);
    }
}
