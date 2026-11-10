<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;
use Shopwell\Core\System\Sms\SmsException;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Register with shopwell.sms.provider to expose a provider and its configuration in Administration.
 * Template submission and approval remain independent, optional capability interfaces.
 */
#[Package('framework')]
abstract class AbstractSmsProvider
{
    abstract public function getTechnicalName(): string;

    abstract public function getLabelSnippet(): string;

    /**
     * @return list<string>
     */
    public function getSupportedRegions(): array
    {
        return ['GLOBAL'];
    }

    public function supportsTemplateMode(): bool
    {
        return false;
    }

    /**
     * @return list<SmsConfigField>
     */
    public function getCredentials(): array
    {
        return [];
    }

    /**
     * @return list<SmsConfigField>
     */
    public function getOptions(): array
    {
        return [];
    }

    /**
     * @return list<array{name: string, type: 'string'|'secret'|'json'|'bool'|'int'|'select', required: bool, labelSnippet: string, default?: string|int|bool, helpSnippet?: string, options?: list<array{value: string|int, labelSnippet: string}>}>
     */
    public function getCredentialDefinitions(): array
    {
        return array_map(static fn (SmsConfigField $field): array => $field->toArray(), $this->getCredentials());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOptionDefinitions(): array
    {
        return array_map(static fn (SmsConfigField $field): array => $field->toArray(), $this->getOptions());
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $options
     */
    abstract public function createTransport(array $credentials, array $options, ?EventDispatcherInterface $dispatcher = null): TransportInterface;

    /**
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     *
     * @throws SmsException a configuration the shop has to fix, thrown as the unrecoverable subclass
     */
    final protected function requireCredentials(array $credentials): void
    {
        $missing = [];

        foreach ($this->getCredentialDefinitions() as $definition) {
            if (!$definition['required']) {
                continue;
            }

            if (($credentials[$definition['name']] ?? null) === null) {
                $missing[] = $definition['name'];
            }
        }

        if ($missing !== []) {
            throw SmsException::credentialsIncomplete($this->getTechnicalName(), $missing);
        }
    }
}
