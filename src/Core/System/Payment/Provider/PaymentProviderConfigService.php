<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;

/**
 * Stores the merchant configuration of the payment interfaces: the credentials of an interface and
 * the parameters of its channels.
 *
 * Everything stored here is sealed with the same cipher the gateway reads with, so the plain text only
 * lives in the answer of the endpoints that hand the configuration to the administration. The values
 * that are not sent stay untouched, which is what makes it possible to edit a single field of an
 * interface without retyping the rest.
 *
 * An interface or channel that is switched on has to be complete, otherwise the first payment of an
 * app would fail with a message only the log sees.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderConfigService
{
    /**
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly PaymentCredentialCipher $cipher,
    ) {
    }

    /**
     * Seals and stores the credentials of an interface. The values that are not sent keep the ones
     * that are stored.
     *
     * @throws PaymentException
     */
    public function saveProvider(PaymentProvider $definition, bool $enabled, mixed $credentials, mixed $settings): void
    {
        $config = $this->findProviderConfig($definition->technicalName);

        $credentials = $this->mergeValues($config === null ? [] : $this->cipher->decrypt($config->credentials), $credentials);
        $settings = array_merge($config->settings ?? [], $this->mapOf($settings));

        if ($enabled) {
            $this->assertComplete($definition->technicalName, $definition->credentials, $credentials);
        }

        $this->providerConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $definition->technicalName,
            'enabled' => $enabled,
            'credentials' => $this->cipher->encrypt($credentials),
            'settings' => $settings,
        ]], Context::createDefaultContext());
    }

    public function resetProvider(string $provider): void
    {
        $config = $this->findProviderConfig($provider);

        if ($config !== null) {
            $this->providerConfigRepository->delete([['id' => $config->id]], Context::createDefaultContext());
        }
    }

    /**
     * Seals and stores the parameters of one channel of an interface. The values that are not sent
     * keep the ones that are stored.
     *
     * @throws PaymentException
     */
    public function saveChannel(PaymentChannel $definition, bool $enabled, mixed $params, mixed $settings): void
    {
        $config = $this->findChannelConfig($definition->providerTechnicalName, $definition->technicalName);

        $params = $this->mergeValues($config === null ? [] : $this->cipher->decrypt($config->params), $params);
        $settings = array_merge($config->settings ?? [], $this->mapOf($settings));

        if ($enabled) {
            $this->assertComplete($definition->technicalName, $definition->params, $params);
        }

        $this->channelConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $definition->providerTechnicalName,
            'channelTechnicalName' => $definition->technicalName,
            'enabled' => $enabled,
            'params' => $this->cipher->encrypt($params),
            'settings' => $settings,
        ]], Context::createDefaultContext());
    }

    public function resetChannel(string $provider, string $channel): void
    {
        $config = $this->findChannelConfig($provider, $channel);

        if ($config !== null) {
            $this->channelConfigRepository->delete([['id' => $config->id]], Context::createDefaultContext());
        }
    }

    public function findProviderConfig(string $provider): ?PaymentProviderConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));
        $criteria->setLimit(1);

        $config = $this->providerConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentProviderConfigEntity ? $config : null;
    }

    /**
     * @return array<string, PaymentChannelConfigEntity>
     */
    public function findChannelConfigs(string $provider): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));

        $configs = [];
        foreach ($this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities() as $config) {
            if ($config instanceof PaymentChannelConfigEntity) {
                $configs[$config->channelTechnicalName] = $config;
            }
        }

        return $configs;
    }

    public function findChannelConfig(string $provider, string $channel): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('providerTechnicalName', $provider),
            new EqualsFilter('channelTechnicalName', $channel)
        );
        $criteria->setLimit(1);

        $config = $this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }

    /**
     * An interface or channel that is switched on has to be complete, otherwise the first payment of
     * an app would fail with a message only the log sees.
     *
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     */
    private function assertComplete(string $technicalName, array $fields, array $values): void
    {
        foreach ($fields as $field) {
            if (!$field->required) {
                continue;
            }

            if (!isset($values[$field->name]) || $values[$field->name] === '') {
                throw PaymentException::missingCredentials($technicalName, $field->name);
            }
        }
    }

    /**
     * The values that are not sent stay untouched: an empty input means "keep what is stored", so a
     * form that only changes one field does not have to submit the rest again.
     *
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>
     */
    private function mergeValues(array $current, mixed $incoming): array
    {
        foreach ($this->mapOf($incoming) as $name => $value) {
            if ((string) $value === '') {
                continue;
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * @return array<string, string>
     */
    private function mapOf(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $name => $item) {
            if (!\is_string($name) || !\is_scalar($item)) {
                continue;
            }

            $map[$name] = (string) $item;
        }

        return $map;
    }
}
