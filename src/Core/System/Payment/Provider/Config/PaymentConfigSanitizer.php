<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config;

use Shopwell\Core\Framework\Log\Package;

/**
 * Removes secret values from a configuration before it is handed to the administration.
 *
 * The key stays with a null value so the caller can still distinguish a configured secret from a
 * configuration that has no values at all. Saving an empty value keeps the stored secret, hence the
 * administration never needs the original to edit the other fields.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentConfigSanitizer
{
    /**
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function values(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            if ($field->secret && \array_key_exists($field->name, $values)) {
                $values[$field->name] = null;
            }
        }

        return $values;
    }
}
