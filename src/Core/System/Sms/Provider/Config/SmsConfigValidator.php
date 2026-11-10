<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Provider\Config;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
final class SmsConfigValidator
{
    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $options
     */
    public function validate(AbstractSmsProvider $provider, array $credentials, array $options): void
    {
        $violations = $this->validateFields($provider->getCredentialDefinitions(), $credentials, 'credentials');

        $violations = array_merge($violations, $this->validateFields($provider->getOptionDefinitions(), $options, 'options'));

        if ($violations !== []) {
            throw SmsException::configurationInvalid($provider->getTechnicalName(), $violations);
        }
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function validateFields(array $definitions, array $values, string $path): array
    {
        $violations = [];

        foreach ($definitions as $field) {
            $name = $field['name'];
            $value = $values[$name] ?? null;

            if ($value === null || (\is_string($value) && trim($value) === '')) {
                if ($field['required']) {
                    $violations[] = $path . '.' . $name;
                }

                continue;
            }

            $valid = match ($field['type']) {
                'bool' => \is_bool($value),
                'int' => \is_int($value),
                'json' => \is_array($value),
                'select' => \in_array($value, array_column($field['options'] ?? [], 'value'), true),
                default => \is_string($value),
            };

            if (!$valid) {
                $violations[] = $path . '.' . $name;
            }
        }

        return $violations;
    }
}
