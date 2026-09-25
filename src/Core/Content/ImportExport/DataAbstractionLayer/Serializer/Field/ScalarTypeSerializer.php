<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopwell\Core\Content\ImportExport\ImportExportException;
use Shopwell\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class ScalarTypeSerializer
{
    private const FILTER_VAR_DEFAULT_FLAGS = ['flags' => \FILTER_NULL_ON_FAILURE];

    public static function deserializeInt(Config $config, Field $field, string $value): ?int
    {
        $filtered = filter_var($value, \FILTER_VALIDATE_INT, self::FILTER_VAR_DEFAULT_FLAGS);

        if (\is_int($filtered)) {
            return $filtered;
        }

        $mapping = $config->getMapping()->get($field->getPropertyName());
        if (!$mapping instanceof Mapping) {
            return null;
        }

        if (self::isValidEmpty($value, $mapping->isRequiredByUser())) {
            return null;
        }

        throw ImportExportException::deserializationFailed($mapping->getMappedKey(), $value, 'integer');
    }

    public static function deserializeBool(Config $config, Field $field, string $value): ?bool
    {
        $filtered = filter_var($value, \FILTER_VALIDATE_BOOLEAN, self::FILTER_VAR_DEFAULT_FLAGS);

        if (\is_bool($filtered)) {
            return $filtered;
        }

        $mapping = $config->getMapping()->get($field->getPropertyName());
        if (!$mapping instanceof Mapping) {
            return null;
        }

        if (self::isValidEmpty($value, $mapping->isRequiredByUser())) {
            return null;
        }

        throw ImportExportException::deserializationFailed($mapping->getMappedKey(), $value, 'boolean');
    }

    private static function isValidEmpty(string $value, bool $isRequiredByUser): bool
    {
        return \trim($value) === '' && !$isRequiredByUser;
    }
}
