<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Input;

use Brick\Math\BigDecimal;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\WorkflowFormOptionsEvent;
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
            throw WorkflowExecutionException::invalidGraph('申请字段数据源不可用');
        }

        return $event->options;
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<array{value: string, label: string}>, dataSource?: string}>
     */
    public function normalize(mixed $schema): array
    {
        if (!\is_array($schema) || !array_is_list($schema) || \count($schema) > 50) {
            throw WorkflowExecutionException::invalidGraph('申请表单最多包含 50 个字段');
        }
        $fields = [];
        $keys = [];
        foreach ($schema as $raw) {
            $field = $raw instanceof \stdClass ? get_object_vars($raw) : $raw;
            if (!\is_array($field) || array_diff(array_keys($field), ['key', 'label', 'type', 'required', 'options', 'dataSource']) !== []
                || !\is_string($field['key'] ?? null) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $field['key']) !== 1 || isset($keys[$field['key']])
                || !\is_string($field['label'] ?? null) || trim($field['label']) === '' || mb_strlen($field['label']) > 255
                || !\in_array($field['type'] ?? null, ['text', 'amount', 'number', 'date', 'select'], true)
                || (\array_key_exists('required', $field) && !\is_bool($field['required']))) {
                throw WorkflowExecutionException::invalidGraph('申请表单字段配置不合法');
            }
            $normalized = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'required' => $field['required'] ?? false];
            if (\array_key_exists('dataSource', $field)) {
                if ($field['type'] !== 'text' || !\is_string($field['dataSource']) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $field['dataSource']) !== 1) {
                    throw WorkflowExecutionException::invalidGraph('字段数据源配置不合法');
                }
                $normalized['dataSource'] = $field['dataSource'];
            }
            if ($field['type'] === 'select') {
                $options = $field['options'] ?? null;
                if (!\is_array($options) || !array_is_list($options) || $options === [] || \count($options) > 50) {
                    throw WorkflowExecutionException::invalidGraph('选项字段需要 1 至 50 个选项');
                }
                $values = [];
                $normalized['options'] = [];
                foreach ($options as $rawOption) {
                    $option = $rawOption instanceof \stdClass ? get_object_vars($rawOption) : $rawOption;
                    if (!\is_array($option) || array_diff(array_keys($option), ['value', 'label']) !== []
                        || !\is_string($option['value'] ?? null) || trim($option['value']) === '' || mb_strlen($option['value']) > 255 || \in_array($option['value'], $values, true)
                        || !\is_string($option['label'] ?? null) || trim($option['label']) === '' || mb_strlen($option['label']) > 255) {
                        throw WorkflowExecutionException::invalidGraph('选项值和名称必须填写，选项值不能重复');
                    }
                    $values[] = $option['value'];
                    $normalized['options'][] = ['value' => $option['value'], 'label' => $option['label']];
                }
            } elseif (\array_key_exists('options', $field)) {
                throw WorkflowExecutionException::invalidGraph('只有选项字段可以配置选项');
            }
            $fields[] = $normalized;
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
            throw WorkflowExecutionException::invalidGraph('申请包含未定义的表单字段');
        }
        $normalized = [];
        foreach ($fields as $field) {
            $value = $data[$field['key']] ?? null;
            if ($value === null || $value === '' || ($field['type'] === 'text' && \is_string($value) && trim($value) === '')) {
                if ($field['required']) {
                    throw WorkflowExecutionException::invalidGraph('请填写：' . $field['label']);
                }
                if (\array_key_exists($field['key'], $data)) {
                    $normalized[$field['key']] = null;
                }
                continue;
            }
            $normalized[$field['key']] = $this->normalizeValue($field, $value);
            if (isset($field['dataSource']) && !\in_array($normalized[$field['key']], array_column($this->options($field['dataSource'], $context), 'value'), true)) {
                throw WorkflowExecutionException::invalidGraph('请选择有效选项：' . $field['label']);
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

        throw WorkflowExecutionException::invalidGraph('字段值不合法：' . $field['label']);
    }
}
